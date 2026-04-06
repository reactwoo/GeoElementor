<?php
/**
 * Plugin Name: Geo Elementor
 * Plugin URI: https://reactwoo.com
 * Description: Advanced geo-targeting solution for Elementor. Create location-based rules for popups, pages, and content. Features include country-based targeting, geo rules management, and seamless Elementor integration via ReactWoo Geo Core and MaxMind GeoLite2 database.
 * Version: 1.0.5.37
 * Author: ReactWoo
 * Author URI: https://reactwoo.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: elementor-geo-popup
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Elementor requires at least: 3.0.0
 * Elementor tested up to: 3.18.0
 * Requires Plugins: elementor, reactwoo-geocore
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('EGP_VERSION', '1.0.5.37');
define('EGP_PLUGIN_FILE', __FILE__);
define('EGP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EGP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EGP_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once EGP_PLUGIN_DIR . 'includes/egp-debug.php';
require_once EGP_PLUGIN_DIR . 'includes/egp-country-data.php';
require_once EGP_PLUGIN_DIR . 'includes/class-egp-editor-context.php';
// WordPress.org-style updates via ReactWoo API (api.reactwoo.com) — same contract as WHMCS Bridge.
require_once EGP_PLUGIN_DIR . 'includes/plugin-updater.php';

// Bootstrap marker only when verbose debug is explicitly enabled (WP_DEBUG + egp_debug_mode).
if (function_exists('egp_is_verbose_log_enabled') && egp_is_verbose_log_enabled() && function_exists('error_log')) {
    error_log('[EGP BOOT] Loaded Geo Elementor ' . EGP_VERSION . ' from ' . EGP_PLUGIN_FILE);
}
if (!is_admin() && function_exists('add_action') && function_exists('egp_is_debug_mode') && egp_is_debug_mode()) {
    add_action('wp_head', function () {
        echo '<script>window.__EGP_BUILD__="' . esc_js(EGP_VERSION) . '";if(window.console&&console.log){console.log("[EGP BUILD]",window.__EGP_BUILD__);}</script>';
    }, 1);
}

// Autoloader for Composer dependencies
if (file_exists(EGP_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once EGP_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Main Plugin Class
 */
class ElementorGeoPopup {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    /**
     * Whether geolocation is properly configured (license + database present)
     * Used to keep plugin inactive until setup is complete
     */
    private $geo_ready = false;
    
    /**
     * Prevent duplicate init when bootstrap runs late.
     */
    private $initialized = false;

    /**
     * Register Elementor integration hooks only once (avoids duplicate work and duplicate downstream notices).
     */
    private $elementor_hooks_registered = false;
    
    /**
     * Get single instance of this class
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        if (did_action('plugins_loaded')) {
            $this->init();
        } else {
            add_action('plugins_loaded', array($this, 'init'), 20); // Increased priority to load after other plugins
        }
        add_filter('doing_it_wrong_trigger_error', array($this, 'filter_known_elementor_dependency_notices'), 10, 4);
        register_activation_hook(EGP_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(EGP_PLUGIN_FILE, array($this, 'deactivate'));
    }

    /**
     * Suppress known Elementor dependency-order notices introduced by WP 6.9.1 debug checks.
     *
     * Keep this scoped to the exact Elementor handles/functions to avoid hiding real issues.
     *
     * @param bool   $trigger  Whether to trigger the doing_it_wrong notice.
     * @param string $function Function name.
     * @param string $message  Notice message.
     * @param string $version  Version argument from _doing_it_wrong.
     * @return bool
     */
    public function filter_known_elementor_dependency_notices($trigger, $function, $message, $version) {
        if (!is_admin()) {
            return $trigger;
        }

        if ('map_meta_cap' === $function && is_string($message) && strpos($message, 'delete_post') !== false) {
            return false;
        }

        if (!in_array($function, array('WP_Styles::add', 'WP_Scripts::add'), true)) {
            return $trigger;
        }

        $msg = (string) $message;
        $is_known = (
            strpos($msg, 'elementor-post-') !== false && strpos($msg, 'elementor-frontend') !== false
        ) || (
            strpos($msg, 'elementor-v2-editor-components') !== false && strpos($msg, 'elementor-v2-editor-') !== false
        );

        return $is_known ? false : $trigger;
    }

    /**
     * Whether to emit verbose info logs
     */
    private function should_log_info() {
        return function_exists('egp_is_verbose_log_enabled') && egp_is_verbose_log_enabled();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Defensive check: ensure WordPress is properly initialized
        if (!function_exists('wp_get_current_user') || !function_exists('add_action')) {
            return;
        }
        
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;

        if ($this->should_log_info()) { error_log('[EGP] Plugin init() called'); }
        if ($this->should_log_info()) {
            add_action('admin_footer', function() {
                echo '<script>if(window.console&&console.log){console.log("[EGP] Plugin init() called");}</script>';
            });
        }

        // Always load admin settings, dashboard, menu, and licensing so settings are visible even if Elementor isn't active
        if (is_admin()) {
            require_once EGP_PLUGIN_DIR . 'includes/centralized-license-manager.php';
            require_once EGP_PLUGIN_DIR . 'includes/geo-database.php';
            require_once EGP_PLUGIN_DIR . 'admin/settings-page.php';
            require_once EGP_PLUGIN_DIR . 'admin/dashboard-page.php';
            require_once EGP_PLUGIN_DIR . 'admin/admin-menu.php';
            require_once EGP_PLUGIN_DIR . 'admin/variant-groups.php';
            require_once EGP_PLUGIN_DIR . 'admin/geo-content-dashboard.php';
            require_once EGP_PLUGIN_DIR . 'includes/licensing.php';
            require_once EGP_PLUGIN_DIR . 'includes/activation-setup.php';
            // Demo helpers are not needed in production; removed from runtime
            // Ensure Geo Rules CPT and admin/AJAX are available in wp-admin regardless of Elementor state
            require_once EGP_PLUGIN_DIR . 'includes/geo-rules.php';
            // Add-on registry UI (listing/install) must load in wp-admin even when Elementor/Pro
            // are inactive — otherwise the Add-Ons screen is empty or shows "manager not available".
            require_once EGP_PLUGIN_DIR . 'includes/addon-base.php';
            require_once EGP_PLUGIN_DIR . 'includes/addon-manager.php';
            // Initialize admin-only components early
            new EGP_Admin_Settings();
            new EGP_Admin_Dashboard();
            new EGP_Admin_Menu();
            new EGP_Licensing();
            // Enable Elementor new template modal deep-link
            self::bootstrap_elementor_new_template_modal();

            // Show Geo Core prerequisite notice until Geo Core is ready.
            add_action('admin_notices', array($this, 'maybe_show_geocore_prereq_notice'), 5);
        }

        // Check if Elementor is active
        if (!did_action('elementor/loaded')) {
            // Info noise suppressed by default
            if ($this->should_log_info()) { error_log('[EGP] Elementor not loaded yet - showing missing notice'); }
            add_action('admin_notices', array($this, 'elementor_missing_notice'));
            return;
        }

        // Check if Elementor Pro is active (robust)
        if (!$this->is_elementor_pro_active()) {
            if ($this->should_log_info()) { error_log('[EGP] Elementor Pro not found (robust check) - showing missing notice'); }
            add_action('admin_notices', array($this, 'elementor_pro_missing_notice'));
            return;
        }

        if ($this->should_log_info()) { error_log('[EGP] Elementor and Pro found - proceeding with full initialization'); }
        
        // Determine readiness before loading geo-dependent components.
        // When ReactWoo Geo Core is present, use its readiness instead of internal MaxMind flags.
        if ( function_exists( 'rwgc_is_ready' ) ) {
            $this->geo_ready = (bool) rwgc_is_ready();
        } else {
            $this->geo_ready = $this->is_geo_ready();
        }

        // Load plugin components
        $this->load_dependencies();
        
        // Load enhanced fixes
        $this->load_enhanced_fixes();
        
        // Add-on manager is loaded earlier for admin; avoid double bootstrap.

        // Register Elementor hooks when Elementor is ready
        add_action('elementor/init', array($this, 'register_elementor_hooks'));

        // Also try to register immediately if Elementor is already loaded
        if (did_action('elementor/loaded')) {
            if ($this->should_log_info()) { error_log('[EGP] Elementor already loaded, registering hooks immediately'); }
            if ($this->should_log_info()) {
                add_action('admin_footer', function() {
                    echo '<script>if(window.console&&console.log){console.log("[EGP] Elementor already loaded - registering hooks immediately");}</script>';
                });
            }
            $this->register_elementor_hooks();
        }

        $this->init_components();
    }
    
    /**
     * Load enhanced fixes
     */
    private function load_enhanced_fixes() {
        // Enhanced fixes are now integrated into the main plugin
        // No separate file needed
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Admin functionality
        if (is_admin()) {
            require_once EGP_PLUGIN_DIR . 'admin/settings-page.php';
            require_once EGP_PLUGIN_DIR . 'admin/popup-editor.php';
        }
        
        // Core functionality - Load in proper order
        // Always load geo-detect to ensure AJAX endpoints and fallbacks are available
        require_once EGP_PLUGIN_DIR . 'includes/geo-detect.php';
        // Always load popup hooks so editor controls appear even if geo isn't ready
        require_once EGP_PLUGIN_DIR . 'includes/popup-hooks.php';
        require_once EGP_PLUGIN_DIR . 'includes/widget-registration.php';
        require_once EGP_PLUGIN_DIR . 'includes/global-settings.php';
        require_once EGP_PLUGIN_DIR . 'includes/dashboard-api.php';
        require_once EGP_PLUGIN_DIR . 'includes/class-egp-ab-testing.php';
        require_once EGP_PLUGIN_DIR . 'includes/class-egp-ai-translation.php';
        require_once EGP_PLUGIN_DIR . 'includes/class-egp-pro-migration.php';
        if (file_exists(EGP_PLUGIN_DIR . 'includes/geo-database.php')) {
            require_once EGP_PLUGIN_DIR . 'includes/geo-database.php';
        }

        // Load geo-rules with defensive check to prevent early loading conflicts
        if (did_action('init')) {
            require_once EGP_PLUGIN_DIR . 'includes/geo-rules.php';
        } else {
            add_action('init', function() {
                require_once EGP_PLUGIN_DIR . 'includes/geo-rules.php';
            }, 5);
        }
        
        // Load native Elementor template integration (hybrid architecture)
        require_once EGP_PLUGIN_DIR . 'includes/elementor-template-integration.php';
        
        // Load Elementor library columns integration (adds geo columns to native library)
        if (is_admin()) {
            require_once EGP_PLUGIN_DIR . 'includes/elementor-library-columns.php';
            require_once EGP_PLUGIN_DIR . 'includes/page-columns-integration.php';
        }
        
        // Safety stop: keep homepage/blog variant group overrides disabled on frontend
        // until runtime routing regressions are fully resolved.
        // require_once EGP_PLUGIN_DIR . 'includes/homepage-variant-group.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize admin components (Editor integration only; settings and licensing were initialized earlier)
        if (is_admin()) {
            new EGP_Popup_Editor();
            // If not configured, prompt admin with a gentle notice and setup link
            if (!$this->geo_ready) {
                add_action('admin_notices', function(){
                    if (!current_user_can('manage_options')) { return; }
                    if ( function_exists( 'rwgc_is_ready' ) ) {
                        $url  = esc_url( admin_url( 'admin.php?page=rwgc-settings' ) );
                        echo '<div class="notice notice-warning"><p>'
                           . esc_html__( 'GeoElementor is waiting for ReactWoo Geo Core to finish setup (MaxMind license and database).', 'elementor-geo-popup' )
                           . ' <a href="' . $url . '">' . esc_html__( 'Open Geo Core settings', 'elementor-geo-popup' ) . '</a>.</p></div>';
                    } else {
                        $url = esc_url(admin_url('admin.php?page=elementor-geo-popup#maxmind'));
                        echo '<div class="notice notice-warning"><p>'
                           . esc_html__('Geo Elementor is inactive until you add your MaxMind license key.', 'elementor-geo-popup')
                           . ' <a href="' . $url . '">' . esc_html__('Add your key in Settings', 'elementor-geo-popup') . '</a>.</p></div>';
                    }
                });
            }
            // Reduce editor console noise: prevent cross-origin Google Fonts requests in Elementor editor
            add_action('elementor/editor/before_enqueue_scripts', function () {
                // Dequeue Elementor's Google fonts style if registered
                wp_dequeue_style('elementor-google-fonts');
                // Prevent Elementor (and Pro) from printing Google fonts in editor
                add_filter('elementor/frontend/print_google_fonts', '__return_false');
                add_filter('elementor_pro/frontend/print_google_fonts', '__return_false');
            }, 999);
            // Provide a tiny favicon to avoid 404s in editor preview
            add_action('admin_head', function () {
                echo '<link rel="icon" href="data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\'/>" />';
            });
        }

        // Also disable Elementor Google Fonts when viewing an Elementor preview on the frontend
        add_action('init', function () {
            if (isset($_GET['elementor-preview'])) {
                add_filter('elementor/frontend/print_google_fonts', '__return_false');
                add_filter('elementor_pro/frontend/print_google_fonts', '__return_false');
            }
        });

        // Extra hardening: stop Elementor from printing local Google Fonts CSS links in editor/preview
        add_action('elementor/frontend/after_register_styles', function(){
            add_filter('elementor/fonts/print_font_links/google', '__return_false');
            if (wp_style_is('elementor-google-fonts', 'registered')) {
                wp_dequeue_style('elementor-google-fonts');
            }
        }, 999);
        
        // Initialize core components (always instantiate to register AJAX and safe fallbacks)
        if (class_exists('EGP_Geo_Detect')) {
            EGP_Geo_Detect::get_instance();
        }
        // Initialize popup hooks in admin/editor regardless of geo readiness so UI controls show
        if (class_exists('EGP_Popup_Hooks')) {
            if (is_admin() || isset($_GET['elementor-preview'])) {
                new EGP_Popup_Hooks();
            } elseif ($this->geo_ready) {
                // Frontend runtime only when geo is ready
                new EGP_Popup_Hooks();
            }
        }
        new EGP_Widget_Registration();
        new EGP_Global_Settings();
        EGP_AB_Testing::init();
        EGP_AI_Translation::init();
        EGP_Pro_Migration::init();
        // Geo Core route extension (Pro variant resolution). Off by default; enable with:
        // add_filter( 'egp_enable_rwgc_route_variant_extension', '__return_true' );
        if ( apply_filters( 'egp_enable_rwgc_route_variant_extension', false ) ) {
            $this->register_rwgc_extensions();
        }
        // Geo Rules system is auto-initialized
    }

    /**
     * Register extension hooks for ReactWoo Geo Core baseline routing.
     */
    private function register_rwgc_extensions() {
        if (!function_exists('rwgc_is_ready') && !class_exists('RWGC_Routing')) {
            return;
        }

        add_filter('rwgc_route_variant_decision', array($this, 'extend_rwgc_route_variant_decision'), 20, 4);
    }

    /**
     * Extend Geo Core free routing decision for Pro users.
     *
     * @param array               $decision Current decision payload.
     * @param array               $config   Geo Core page config.
     * @param int|null            $page_id  Page ID (Geo Core 4-arg filter).
     * @param \RWGC_Context|null  $context  Visitor context when available.
     * @return array
     */
    public function extend_rwgc_route_variant_decision($decision, $config, $page_id = null, $context = null) {
        if (!$this->is_license_valid() || !class_exists('RW_Geo_Router')) {
            return $decision;
        }

        $country = isset($decision['country']) ? strtoupper(sanitize_text_field($decision['country'])) : '';
        if ('' === $country) {
            return $decision;
        }

        $target = 0;
        try {
            $router = RW_Geo_Router::get_instance();
            $master_page_id = isset($decision['page_id']) ? absint($decision['page_id']) : 0;
            if ($master_page_id <= 0 && null !== $page_id) {
                $master_page_id = absint($page_id);
            }
            if ($master_page_id > 0) {
                $master_page_id = egp_resolve_rwgc_master_page_id($master_page_id);
            }
            $variant = $router->get_active_variant_group_for_route($master_page_id);
            if (!$variant) {
                return $decision;
            }
            $mapping = $router->resolve_mapping($variant, $country);
            if ($mapping && !empty($mapping->page_id)) {
                $target = absint($mapping->page_id);
            } elseif (!empty($variant->default_page_id)) {
                $target = absint($variant->default_page_id);
            }
        } catch (\Throwable $e) {
            return $decision;
        }

        if ($target > 0) {
            $decision['target_page_id'] = $target;
            $decision['reason'] = 'egp_pro_variant';
        }

        return $decision;
    }

    /**
     * Robust check for Elementor Pro activation
     */
    private function is_elementor_pro_active() {
        // Multiple signals that Pro is active
        $by_class = class_exists('ElementorPro\\Plugin');
        $by_const = defined('ELEMENTOR_PRO_VERSION');
        $by_action = did_action('elementor_pro/init');
        $active = ($by_class || $by_const || $by_action);
        /** Allow override by integrations/tests */
        return (bool) apply_filters('egp_is_elementor_pro_active', $active, array(
            'by_class' => $by_class,
            'by_const' => $by_const,
            'by_action' => $by_action,
        ));
    }

    /**
     * Admin helper: open Elementor "Add New" modal on Templates screen when flagged
     */
    public static function bootstrap_elementor_new_template_modal() {
        add_action('admin_head-edit.php', function () {
            $is_el_lib = isset($_GET['post_type']) && $_GET['post_type'] === 'elementor_library';
            $should_open = isset($_GET['rw_open_new']) && $_GET['rw_open_new'] === '1';
            if (!$is_el_lib || !$should_open) { return; }
            ?>
            <script>
            jQuery(function($){
                var $btn = $('.wrap .page-title-action').first();
                if ($btn.length) { $btn.trigger('click'); return; }
                var $alt = $('.elementor-add-new-template, .page-title-action:contains("Add New")').first();
                if ($alt.length) { $alt.trigger('click'); }
            });
            </script>
            <?php
        });
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create necessary directories
        $this->create_directories();
        
        // Create database tables if needed
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();

        // Geo Core prerequisite: keep a sticky admin notice until Geo Core is ready.
        if ( ! function_exists( 'rwgc_is_ready' ) ) {
            update_option( 'egp_needs_geocore', 1 );
        } else {
            try {
                if ( ! rwgc_is_ready() ) {
                    update_option( 'egp_needs_geocore', 1 );
                }
            } catch ( \Throwable $e ) {
                update_option( 'egp_needs_geocore', 1 );
            }
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Trigger activation hooks
        do_action('rw_geo_after_activation');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('egp_update_geo_database');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Trigger deactivation hooks
        do_action('rw_geo_after_deactivation');
    }

    /**
     * Register Elementor hooks when Elementor is initialized
     */
    public function register_elementor_hooks() {
        if ($this->elementor_hooks_registered) {
            return;
        }
        $this->elementor_hooks_registered = true;

        // Debug logging
        if ($this->should_log_info()) { error_log('[EGP] register_elementor_hooks() called - Elementor loaded: ' . (did_action('elementor/loaded') ? 'YES' : 'NO')); }
        
        // This is the key: register controls immediately using the proper hooks
        // Following the if-so pattern which is proven to work
            $this->register_elementor_geo_controls();
    }

    /**
     * Get country options array for Elementor controls (canonical: assets/data/countries.json).
     */
    private function get_country_options() {
        return function_exists( 'egp_get_country_options' ) ? egp_get_country_options() : array();
    }


    
    /**
     * Get country options formatted for Elementor's CHOOSE control
     */
    private function get_country_options_choose() {
        // Defensive check: ensure get_country_options method exists
        if (!method_exists($this, 'get_country_options')) {
            return array();
        }

        $countries = $this->get_country_options();
        $choose_options = array();

        // Map regions to Elementor icons for better visual organization
        $region_icons = array(
            // Europe
            'GB' => 'eicon-flag', 'DE' => 'eicon-flag', 'FR' => 'eicon-flag', 'IT' => 'eicon-flag',
            'ES' => 'eicon-flag', 'NL' => 'eicon-flag', 'BE' => 'eicon-flag', 'CH' => 'eicon-flag',
            'AT' => 'eicon-flag', 'SE' => 'eicon-flag', 'NO' => 'eicon-flag', 'DK' => 'eicon-flag',
            'FI' => 'eicon-flag', 'IE' => 'eicon-flag', 'PT' => 'eicon-flag', 'GR' => 'eicon-flag',
            'PL' => 'eicon-flag', 'CZ' => 'eicon-flag', 'HU' => 'eicon-flag', 'SK' => 'eicon-flag',
            'SI' => 'eicon-flag', 'HR' => 'eicon-flag', 'BA' => 'eicon-flag', 'RS' => 'eicon-flag',
            'ME' => 'eicon-flag', 'MK' => 'eicon-flag', 'AL' => 'eicon-flag', 'BG' => 'eicon-flag',
            'RO' => 'eicon-flag', 'MD' => 'eicon-flag', 'UA' => 'eicon-flag', 'BY' => 'eicon-flag',

            // North America
            'US' => 'eicon-star', 'CA' => 'eicon-star', 'MX' => 'eicon-star',

            // Asia
            'JP' => 'eicon-star-o', 'CN' => 'eicon-star-o', 'KR' => 'eicon-star-o', 'IN' => 'eicon-star-o',
            'TH' => 'eicon-star-o', 'VN' => 'eicon-star-o', 'MY' => 'eicon-star-o', 'SG' => 'eicon-star-o',

            // Oceania
            'AU' => 'eicon-star', 'NZ' => 'eicon-star',

            // South America
            'BR' => 'eicon-heart', 'AR' => 'eicon-heart', 'CL' => 'eicon-heart', 'CO' => 'eicon-heart',
            'PE' => 'eicon-heart', 'VE' => 'eicon-heart', 'EC' => 'eicon-heart', 'UY' => 'eicon-heart',
            'PY' => 'eicon-heart', 'BO' => 'eicon-heart',

            // Africa
            'ZA' => 'eicon-star-o', 'NG' => 'eicon-star-o', 'EG' => 'eicon-star-o', 'KE' => 'eicon-star-o',
            'MA' => 'eicon-star-o', 'TN' => 'eicon-star-o', 'GH' => 'eicon-star-o', 'ET' => 'eicon-star-o',

            // Middle East
            'AE' => 'eicon-star-o', 'SA' => 'eicon-star-o', 'IL' => 'eicon-star-o', 'TR' => 'eicon-star-o',
            'IR' => 'eicon-star-o', 'IQ' => 'eicon-star-o', 'JO' => 'eicon-star-o', 'LB' => 'eicon-star-o',
        );

        foreach ($countries as $code => $name) {
            // Use region-specific icon or fallback to a generic one
            $icon = isset($region_icons[$code]) ? $region_icons[$code] : 'eicon-map-marker';

            $choose_options[$code] = array(
                'title' => $name,
                'icon' => $icon,
            );
        }

        return $choose_options;
    }

    /**
     * Markup for Geo Core visitor city/region/IP in the Elementor panel (Pro geo section).
     *
     * @return string Empty when Geo Core is not active or not ready.
     */
    private function get_rwgc_visitor_preview_html() {
        if (!function_exists('rwgc_is_ready') || !rwgc_is_ready() || !function_exists('rwgc_get_visitor_data')) {
            return '';
        }
        $d = rwgc_get_visitor_data();
        $ip = isset($d['ip']) ? (string) $d['ip'] : '';
        $cc = isset($d['country_code']) ? strtoupper((string) $d['country_code']) : '';
        $cn = isset($d['country_name']) ? (string) $d['country_name'] : '';
        $city = isset($d['city']) ? (string) $d['city'] : '';
        $region = isset($d['region']) ? (string) $d['region'] : '';
        $line1 = $cc;
        if ($cn !== '') {
            $line1 .= ' (' . $cn . ')';
        }
        return '<div style="margin-bottom:10px;padding:8px;border:1px solid #bbf7d0;border-radius:4px;background:#f0fdf4;font-size:12px;line-height:1.5;color:#166534;">'
            . '<strong>' . esc_html__('Detected for your connection', 'elementor-geo-popup') . '</strong><br>'
            . esc_html($line1 !== '' ? $line1 : '—') . '<br>'
            . esc_html__('City', 'elementor-geo-popup') . ': ' . esc_html($city !== '' ? $city : '—') . '<br>'
            . esc_html__('Region', 'elementor-geo-popup') . ': ' . esc_html($region !== '' ? $region : '—') . '<br>'
            . esc_html__('IP', 'elementor-geo-popup') . ': ' . esc_html($ip !== '' ? $ip : '—')
            . '</div>';
    }

    /**
     * Register Geo Targeting controls for Elementor elements
     * Following the if-so pattern which is proven to work with Elementor
     */
    private function register_elementor_geo_controls() {
        // Prevent multiple registrations
        static $controls_registered = false;
        if ($controls_registered) {
            return;
        }

        if ($this->should_log_info()) { error_log('[EGP] Registering Elementor geo controls using if-so pattern'); }

        // KEY FIX: Use 'common/_section_style' hook for ALL widgets (this is what if-so does)
        // This is the standard way to add controls to all widgets at once
        add_action('elementor/element/common/_section_style/after_section_end', array($this, 'add_geo_targeting_controls'), 10, 2);
        
        // Add controls to Sections
        add_action('elementor/element/section/section_advanced/after_section_end', array($this, 'add_geo_targeting_controls'), 10, 2);
        
        // Add controls to Columns
        add_action('elementor/element/column/section_advanced/after_section_end', array($this, 'add_geo_targeting_controls'), 10, 2);
        
        // Add controls to Containers (Elementor 3.x)
        add_action('elementor/element/container/section_layout/after_section_end', array($this, 'add_geo_targeting_controls'), 10, 2);
        
        // Add controls to Popups (Elementor Pro)
        add_action('elementor/element/popup/section_advanced/after_section_end', array($this, 'add_geo_targeting_controls'), 10, 2);

        if ($this->should_log_info()) { error_log('[EGP] Geo controls hooks registered successfully'); }

        // Mark as registered
        $controls_registered = true;
    }


    /**
     * Add Geo Targeting controls to an Elementor element
     * This method is called by the registered hooks for each element type
     */
    public function add_geo_targeting_controls($element, $args = null) {
        // Geo Core now provides baseline Elementor controls in free mode.
        // Avoid duplicate/conflicting control sections when Geo Core is active.
        $rwgc_basic_active = class_exists('RWGC_Elementor') || function_exists('rwgc_is_ready');

        // Check if geo controls already exist to prevent duplicates
        $controls = $element->get_controls();
        if (is_array($controls) && isset($controls['egp_geo_tools'])) {
            return;
        }

        // Determine Pro gating - check license status instead of WooCommerce capability
        $is_pro = $this->is_license_valid() || apply_filters('egp_is_pro_user', false);

        if (!$is_pro && $rwgc_basic_active) {
            return;
        }

        $element->start_controls_section(
            'egp_geo_tools',
            array(
                'label' => __('Geo Targeting', 'elementor-geo-popup'),
                'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
            )
        );

        // Add element ID display for targeting reference
        $element->add_control(
            'egp_element_id',
            array(
                'label'       => __('Element ID', 'elementor-geo-popup'),   
                'type'        => \Elementor\Controls_Manager::TEXT,
                'description' => __('Unique ID for this element. Used in Rules/Groups for targeting. Leave empty to auto-generate.', 'elementor-geo-popup'),
                'placeholder' => __('Leave empty to auto-generate', 'elementor-geo-popup'),                                                                 
                'default'     => '',
            )
        );

        $element->add_control(
            'egp_element_id_display',
            array(
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw'  => '<div style="background:#f8f9fa;border:1px solid #dee2e6;padding:8px;border-radius:4px;margin-bottom:10px;">'                    
                        . '<strong>' . esc_html__('Element ID:', 'elementor-geo-popup') . '</strong> '                                                     
                        . '<code id="egp-element-id-display" style="background:#e9ecef;padding:2px 6px;border-radius:3px;">—</code> '                      
                        . '<button type="button" id="egp-copy-element-id" class="elementor-button elementor-button-default" style="padding:2px 6px;font-size:11px;">' . esc_html__('Copy', 'elementor-geo-popup') . '</button>'            
                        . '<br><small style="color:#6c757d;">' . esc_html__('Use this ID in Rules or Groups when targeting by Elementor element ID.', 'elementor-geo-popup') . '</small></div>',                                           
                'content_classes' => 'egp-element-info',
            )
        );

        $rwgc_preview = $this->get_rwgc_visitor_preview_html();
        if ($rwgc_preview !== '') {
            $element->add_control(
                'egp_visitor_geo_preview',
                array(
                    'type' => \Elementor\Controls_Manager::RAW_HTML,
                    'raw' => $rwgc_preview,
                    'content_classes' => 'egp-visitor-geo-preview',
                )
            );
        }

        if ($is_pro) {
            $element->add_control(
                'egp_geo_enabled',
                array(
                    'label'        => __('Enable Geo Targeting', 'elementor-geo-popup'),
                    'type'         => \Elementor\Controls_Manager::SWITCHER,
                    'label_on'     => __('On', 'elementor-geo-popup'),
                    'label_off'    => __('Off', 'elementor-geo-popup'),
                    'return_value' => 'yes',
                    'default'      => '',
                )
            );

            // Use native SELECT control with multiple=true for better persistence
            $element->add_control(
                'egp_countries_html',
                array(
                    'type'        => \Elementor\Controls_Manager::RAW_HTML,
                    'raw'         => (function(){
                        $opts = $this->get_country_options();
                        $html = '<div class="egp-countries-native"><label class="elementor-control-title">'.esc_html__('Target Countries', 'elementor-geo-popup').'</label><div class="elementor-control-input-wrapper">';
                        $html .= '<select id="egp_countries_native" class="egp-country-select" multiple size="12" style="width:100%;max-width:100%;min-height:220px;">';
                        foreach ($opts as $code => $name) { $html .= '<option value="'.esc_attr($code).'">'.esc_html($name).'</option>'; }                 
                        $html .= '</select><p class="description">'.esc_html__('Hold Ctrl/Cmd to select multiple countries.', 'elementor-geo-popup').'</p></div></div>';
                        return $html;
                    })(),
                    'condition'   => array('egp_geo_enabled' => 'yes'),    
                )
            );
            $element->add_control(
                'egp_countries',
                array(
                    'type'        => \Elementor\Controls_Manager::HIDDEN,
                    'default'     => '',
                    'condition'   => array('egp_geo_enabled' => 'yes'),
                )
            );

            // Priority control
            $element->add_control(
                'egp_geo_priority',
                array(
                    'label'       => __('Priority', 'elementor-geo-popup'),
                    'type'        => \Elementor\Controls_Manager::NUMBER,
                    'min'         => 1,
                    'max'         => 100,
                    'step'        => 1,
                    'default'     => 50,
                    'condition'   => array('egp_geo_enabled' => 'yes'),
                    'description' => __('Higher numbers take precedence (1-100)', 'elementor-geo-popup'),
                )
            );

            // Tracking ID
            $element->add_control(
                'egp_geo_tracking_id',
                array(
                    'label'       => __('Tracking ID', 'elementor-geo-popup'),
                    'type'        => \Elementor\Controls_Manager::TEXT,
                    'condition'   => array('egp_geo_enabled' => 'yes'),
                    'description' => __('Used for analytics and tracking', 'elementor-geo-popup'),
                    'placeholder' => __('Auto-generated if empty', 'elementor-geo-popup'),
                )
            );

			// (Legacy UI replaced by native select above)
        } else {
            // Free: show upgrade callout; do not expose controls
            $element->add_control(
                'egp_geo_upgrade',
                array(
                    'type' => \Elementor\Controls_Manager::RAW_HTML,
                    'raw'  => '<div style="background:#fff3cd;border:1px solid #ffeaa7;color:#856404;padding:12px;border-radius:4px;margin-top:10px;">'
                            . '<strong>' . esc_html__('Pro Feature', 'elementor-geo-popup') . '</strong><br>'
                            . esc_html__('Upgrade to Pro to target Sections, Containers, Columns, Widgets, and Forms by country.', 'elementor-geo-popup')
                            . '</div>',
                    'content_classes' => 'egp-upgrade-box',
                )
            );
        }

        $element->end_controls_section();
    }

    /**
     * Create necessary directories
     */
    private function create_directories() {
        $upload_dir = wp_upload_dir();
        $geo_dir = $upload_dir['basedir'] . '/geo-popup-db';
        
        if (!file_exists($geo_dir)) {
            wp_mkdir_p($geo_dir);
        }
        
        // Create .htaccess to protect database files
        $htaccess_file = $geo_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents($htaccess_file, $htaccess_content);
        }
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'egp_popup_countries';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            popup_id bigint(20) NOT NULL,
            countries longtext NOT NULL,
            enabled tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY popup_id (popup_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = array(
            'maxmind_license_key' => '',
            'database_path' => '',
            'last_update' => '',
            'auto_update' => false,
            'debug_mode' => false,
            'default_popup_id' => '',
            'fallback_behavior' => 'show_to_all'
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option('egp_' . $key) === false) {
                add_option('egp_' . $key, $value);
            }
        }
    }

    /**
     * Determine whether geolocation is configured well enough to enable features
     */
    private function is_geo_ready() {
        $ready = false;
        try {
            $key  = get_option('egp_maxmind_license_key');
            $path = get_option('egp_database_path');
            if (!empty($key) && !empty($path) && file_exists($path) && is_readable($path)) {
                $ready = true;
            }
        } catch (\Throwable $e) {
            $ready = false;
        }
        /** Allow integrations to override readiness (e.g., alternate providers) */
        return (bool) apply_filters('egp_geo_ready', $ready);
    }
    
    /**
     * Elementor missing notice
     */
    public function elementor_missing_notice() {
        $message = sprintf(
            __('Geo Elementor requires %1$sElementor%2$s plugin to be installed and activated.', 'elementor-geo-popup'),
            '<a href="' . esc_url(admin_url('plugin-install.php?s=Elementor&tab=search&type=term')) . '">',
            '</a>'
        );
        
        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }
    
    /**
     * Elementor Pro missing notice
     */
    public function elementor_pro_missing_notice() {
        $message = sprintf(
            __('Geo Elementor requires %1$sElementor Pro%2$s plugin to be installed and activated.', 'elementor-geo-popup'),
            '<a href="' . esc_url(admin_url('plugin-install.php?s=Elementor+Pro&tab=search&type=term')) . '">',
            '</a>'
        );
        
        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }

    /**
     * Admin notice: GeoElementor requires Geo Core to be installed and ready.
     *
     * @return void
     */
    public function maybe_show_geocore_prereq_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $needs = (bool) get_option( 'egp_needs_geocore', false );

        $core_ready = false;
        if ( function_exists( 'rwgc_is_ready' ) ) {
            try {
                $core_ready = (bool) rwgc_is_ready();
            } catch ( \Throwable $e ) {
                $core_ready = false;
            }
        }

        // If Geo Core is ready, clear the sticky flag and stop showing.
        if ( $core_ready ) {
            if ( $needs ) {
                delete_option( 'egp_needs_geocore' );
            }
            return;
        }

        // Only show the warning when Geo Core is not ready AND GeoElementor was activated.
        if ( ! $needs ) {
            // Activation should set this, but keep it conservative.
            return;
        }

        $core_settings_url  = esc_url( admin_url( 'admin.php?page=rwgc-settings' ) );
        $plugin_install_url = esc_url( admin_url( 'plugin-install.php?s=reactwoo+geo+core&tab=search&type=term' ) );

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__(
            'GeoElementor requires ReactWoo Geo Core to be installed and activated first. Geo Core manages the MaxMind database updates and shared country detection.',
            'elementor-geo-popup'
        );
        echo ' <a href="' . $core_settings_url . '">' . esc_html__( 'Open Geo Core Settings', 'elementor-geo-popup' ) . '</a>';
        echo ' · <a href="' . $plugin_install_url . '">' . esc_html__( 'Install Geo Core', 'elementor-geo-popup' ) . '</a>';
        echo '</p></div>';
    }
    
    /**
     * Check if plugin license is valid
     * 
     * @return bool True if license is valid, false otherwise
     */
    private function is_license_valid() {
        // Check if license manager is available
        if (!class_exists('EGP_Centralized_License_Manager')) {
            return false;
        }
        
        $license_manager = EGP_Centralized_License_Manager::get_instance();
        $license_data = $license_manager->get_license_data('geo-elementor', null, false);
        
        // Check if license data is valid
        if (is_wp_error($license_data)) {
            return false;
        }
        
        return isset($license_data['valid']) && $license_data['valid'];
    }
}

// Initialize the plugin
ElementorGeoPopup::get_instance();

