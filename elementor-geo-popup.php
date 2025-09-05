<?php
/**
 * Plugin Name: Geo Elementor
 * Plugin URI: https://reactwoo.com
 * Description: Advanced geo-targeting solution for Elementor. Create location-based rules for popups, pages, and content. Features include country-based targeting, geo rules management, and seamless Elementor integration with MaxMind GeoLite2 database.
 * Version: 1.0.0
 * Author: ReactWoo
 * Author URI: https://reactwoo.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: elementor-geo-popup
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Elementor requires at least: 3.0.0
 * Elementor tested up to: 3.18.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('EGP_VERSION', '1.0.0');
define('EGP_PLUGIN_FILE', __FILE__);
define('EGP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EGP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EGP_PLUGIN_BASENAME', plugin_basename(__FILE__));

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
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(EGP_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(EGP_PLUGIN_FILE, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        error_log('[EGP] Plugin init() called');
        add_action('admin_footer', function() {
            echo '<script>console.log("[EGP] Plugin init() called");</script>';
        });

        // Always load admin settings, dashboard, menu, and licensing so settings are visible even if Elementor isn't active
        if (is_admin()) {
            require_once EGP_PLUGIN_DIR . 'includes/centralized-license-manager.php';
            require_once EGP_PLUGIN_DIR . 'includes/geo-database.php';
            require_once EGP_PLUGIN_DIR . 'admin/settings-page.php';
            require_once EGP_PLUGIN_DIR . 'admin/dashboard-page.php';
            require_once EGP_PLUGIN_DIR . 'admin/admin-menu.php';
            require_once EGP_PLUGIN_DIR . 'admin/variant-groups.php';
            require_once EGP_PLUGIN_DIR . 'includes/licensing.php';
            require_once EGP_PLUGIN_DIR . 'includes/activation-setup.php';
            require_once EGP_PLUGIN_DIR . 'demo-fallback-system.php';
            // Ensure Geo Rules CPT and admin/AJAX are available in wp-admin regardless of Elementor state
            require_once EGP_PLUGIN_DIR . 'includes/geo-rules.php';
            // Initialize admin-only components early
            new EGP_Admin_Settings();
            new EGP_Admin_Dashboard();
            new EGP_Admin_Menu();
            new EGP_Licensing();
            // Enable Elementor new template modal deep-link
            self::bootstrap_elementor_new_template_modal();
        }

        // Check if Elementor is active
        if (!did_action('elementor/loaded')) {
            error_log('[EGP] Elementor not loaded yet - showing missing notice');
            add_action('admin_notices', array($this, 'elementor_missing_notice'));
            return;
        }

        // Check if Elementor Pro is active
        if (!class_exists('ElementorPro\Plugin')) {
            error_log('[EGP] Elementor Pro not found - showing missing notice');
            add_action('admin_notices', array($this, 'elementor_pro_missing_notice'));
            return;
        }

        error_log('[EGP] Elementor and Pro found - proceeding with full initialization');
        
        // Determine readiness before loading geo-dependent components
        $this->geo_ready = $this->is_geo_ready();

        // Load plugin components
        $this->load_dependencies();

        // Register Elementor hooks when Elementor is ready
        add_action('elementor/init', array($this, 'register_elementor_hooks'));

        // Also try to register immediately if Elementor is already loaded
        if (did_action('elementor/loaded')) {
            error_log('[EGP] Elementor already loaded, registering hooks immediately');
            add_action('admin_footer', function() {
                echo '<script>console.log("[EGP] Elementor already loaded - registering hooks immediately");</script>';
            });
            $this->register_elementor_hooks();
        }

        $this->init_components();
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
        // Only load frontend geo features if configured to prevent WSODs
        if ($this->geo_ready) {
            require_once EGP_PLUGIN_DIR . 'includes/geo-detect.php';
            require_once EGP_PLUGIN_DIR . 'includes/popup-hooks.php';
        }
        require_once EGP_PLUGIN_DIR . 'includes/widget-registration.php';
        require_once EGP_PLUGIN_DIR . 'includes/global-settings.php';
        require_once EGP_PLUGIN_DIR . 'includes/dashboard-api.php';
        require_once EGP_PLUGIN_DIR . 'includes/geo-rules.php';
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
                    $url = esc_url(admin_url('admin.php?page=elementor-geo-popup#maxmind'));
                    echo '<div class="notice notice-warning"><p>'
                       . esc_html__('Geo Elementor is inactive until you add your MaxMind license key.', 'elementor-geo-popup')
                       . ' <a href="' . $url . '">' . esc_html__('Add your key in Settings', 'elementor-geo-popup') . '</a>.</p></div>';
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
        
        // Initialize core components
        EGP_Geo_Detect::get_instance();
        new EGP_Popup_Hooks();
        new EGP_Widget_Registration();
        new EGP_Global_Settings();
        // Geo Rules system is auto-initialized
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
        // Debug logging - always log this
        error_log('[EGP] register_elementor_hooks() called - Elementor loaded: ' . (did_action('elementor/loaded') ? 'YES' : 'NO'));
        error_log('[EGP] Elementor Plugin class exists: ' . (class_exists('\Elementor\Plugin') ? 'YES' : 'NO'));

        // Add inline script to console for immediate feedback
        add_action('admin_footer', function() {
            echo '<script>console.log("[EGP] PHP: register_elementor_hooks() executed");</script>';
        });

        // Only register if Elementor is available and loaded
        if (did_action('elementor/loaded') && class_exists('\Elementor\Plugin')) {
            error_log('[EGP] Calling register_elementor_geo_controls()');
            $this->register_elementor_geo_controls();
        } else {
            // Fallback: register on a later hook if Elementor isn't fully loaded yet
            error_log('[EGP] Setting up fallback hook on elementor/init');
            add_action('elementor/init', array($this, 'register_elementor_geo_controls'), 20);
        }
    }

    /**
     * Register Geo Targeting controls for Elementor elements
     */
    private function register_elementor_geo_controls() {
        // Use Elementor's proper hook system - register controls for all element types
        error_log('[EGP] register_elementor_geo_controls() called');
        add_action('admin_footer', function() {
            echo '<script>console.log("[EGP] register_elementor_geo_controls() called");</script>';
        });

        // Try using Elementor's widget registration method instead of hooks
        // This is the most reliable way to add controls to all widgets
        add_action('elementor/widgets/widgets_registered', function($widgets_manager) {
            error_log('[EGP] Widgets registered hook fired');

            // Get all registered widgets
            $widget_types = $widgets_manager->get_widget_types();
            error_log('[EGP] Found ' . count($widget_types) . ' widget types');

            // Add controls to each widget type
            foreach ($widget_types as $widget_type => $widget) {
                error_log('[EGP] Processing widget: ' . $widget_type);
                $this->add_geo_controls_to_widget($widget);
            }
        });

        // Add support for containers (Elementor 3.0+)
        add_action('elementor/elements/elements_registered', function($elements_manager) {
            error_log('[EGP] Elements registered hook fired');

            // Get container element if it exists
            if (method_exists($elements_manager, 'get_element_types')) {
                $element_types = $elements_manager->get_element_types();
                if (isset($element_types['container'])) {
                    $container = $element_types['container'];
                    error_log('[EGP] Found container element, adding geo controls');
                    $this->add_geo_controls_to_container($container);
                }
            }
        });

        // Remove broad after_section_end hooks for production to avoid duplication or re-entrancy

        error_log('[EGP] Elementor controls registration completed');

        // Test if our hooks are actually registered by checking WordPress action registry
        global $wp_filter;
        $test_hooks = array(
            'elementor/element/common/section_advanced/after_section_end',
            'elementor/element/section/section_advanced/after_section_end',
            'elementor/element/container/section_advanced/after_section_end'
        );

        foreach ($test_hooks as $hook) {
            $has_hook = isset($wp_filter[$hook]) && !empty($wp_filter[$hook]);
            error_log('[EGP] Hook registered check - ' . $hook . ': ' . ($has_hook ? 'YES' : 'NO'));
        }

        // Add a test hook to verify Elementor is working
        add_action('elementor/editor/after_enqueue_scripts', function() {
            error_log('[EGP] Elementor editor scripts enqueued - editor is loading');
        });

        // Add test hook for when elements are initialized
        add_action('elementor/frontend/element_ready/global', function($element) {
            error_log('[EGP] Elementor frontend element ready - Elementor is working');
        });

        // Add console debugging
        add_action('admin_footer', function() {
            ?>
            <script>
                console.log('[EGP] JavaScript debugging loaded');

                // Check if Elementor is available
                if (typeof elementor !== 'undefined') {
                    console.log('[EGP] Elementor is available');

                    // Listen for panel opening
                    if (elementor.channels && elementor.channels.editor) {
                        elementor.channels.editor.on('section:activated', function(sectionName, editor) {
                            console.log('[EGP] Section activated:', sectionName);
                        });

                        elementor.channels.editor.on('element:clicked', function(model) {
                            console.log('[EGP] Element clicked:', model.get('elType'));
                        });
                    }

                    // Listen for panel opening via hooks
                    elementor.hooks.addAction('panel/open_editor/widget', function(panel, model) {
                        console.log('[EGP] Widget editor opened:', model.get('widgetType'));
                    });

                    elementor.hooks.addAction('panel/open_editor/section', function(panel, model) {
                        console.log('[EGP] Section editor opened');
                    });

                    elementor.hooks.addAction('panel/open_editor/container', function(panel, model) {
                        console.log('[EGP] Container editor opened');
                    });

                    elementor.hooks.addAction('panel/open_editor/column', function(panel, model) {
                        console.log('[EGP] Column editor opened');
                    });

                } else {
                    console.log('[EGP] Elementor not available');
                }

                // Listen for any DOM changes that might indicate element panel opening
                document.addEventListener('DOMNodeInserted', function(e) {
                    if (e.target.classList && e.target.classList.contains('elementor-panel')) {
                        console.log('[EGP] Elementor panel inserted');
                    }
                });

                // Check for Elementor panel every second for 30 seconds
                var checkCount = 0;
                var checkInterval = setInterval(function() {
                    checkCount++;
                    var panels = document.querySelectorAll('.elementor-panel');
                    if (panels.length > 0) {
                        console.log('[EGP] Found Elementor panels:', panels.length);
                        clearInterval(checkInterval);
                    }
                    if (checkCount >= 30) {
                        console.log('[EGP] No Elementor panels found after 30 seconds');
                        clearInterval(checkInterval);
                    }
                }, 1000);
            </script>
            <?php
        });

        // Test basic Elementor hooks
        add_action('elementor/element/after_add_attributes', function($element) {
            static $logged = false;
            if (!$logged) {
                error_log('[EGP] Basic Elementor hook fired - Elementor is working');
                $logged = true;
            }
        });

        // Removed general element hook to prevent infinite loops
    }

    /**
     * Add Geo Targeting controls to a widget using Elementor's API
     */
    private function add_geo_controls_to_widget($widget) {
        // Check if widget already has our controls
        $controls = $widget->get_controls();
        if (isset($controls['egp_geo_tools'])) {
            error_log('[EGP] Widget ' . $widget->get_name() . ' already has geo controls');
            return;
        }

        error_log('[EGP] Adding geo controls to widget: ' . $widget->get_name());

        // Inject our section at the START of the Advanced tab (Elementor injection API)
        if (method_exists($widget, 'start_injection')) {
            $widget->start_injection(array(
                'type' => 'section',
                'at'   => 'start',
                'of'   => 'section_advanced',
            ));
        }

        // Add the controls section at the TOP of Advanced tab
        $widget->start_controls_section(
            'egp_geo_tools',
            array(
                'label' => __('Geo Targeting', 'elementor-geo-popup'),
                'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
            ),
            array(
                'priority' => 1, // High priority to show at top
            )
        );

        // If not configured, show setup callout and return
        if (!$this->geo_ready) {
            $settings_url = admin_url('admin.php?page=elementor-geo-popup#maxmind');
            $widget->add_control(
                'egp_geo_setup_notice',
                array(
                    'type' => \Elementor\Controls_Manager::RAW_HTML,
                    'raw'  => '<div style="background:#fff8e5;border:1px solid #ffe08a;padding:10px;border-radius:4px;">'
                           . esc_html__('Geo Targeting is inactive until you add your MaxMind license key.', 'elementor-geo-popup')
                           . ' <a href="' . esc_url($settings_url) . '" target="_blank">' . esc_html__('Open Settings', 'elementor-geo-popup') . '</a>'
                           . '</div>',
                )
            );
            $widget->end_controls_section();
            if (method_exists($widget, 'end_injection')) { $widget->end_injection(); }
            return;
        }

        // Add element ID control with auto-generated value
        $widget->add_control(
            'egp_element_id',
            array(
                'label'       => __('Element ID', 'elementor-geo-popup'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'description' => __('Unique ID for this element. Used in Rules/Groups for targeting. Leave empty to auto-generate.', 'elementor-geo-popup'),
                'placeholder' => __('Leave empty to auto-generate', 'elementor-geo-popup'),
                'default'     => '',
            )
        );

        // Add Pro controls if user has Pro
        $is_pro = current_user_can('manage_woocommerce') || apply_filters('egp_is_pro_user', false);

        if ($is_pro) {
            $widget->add_control(
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

            $widget->add_control(
                'egp_geo_countries_store',
                array(
                    'type'        => \Elementor\Controls_Manager::HIDDEN,
                    'condition'   => array('egp_geo_enabled' => 'yes'),
                    'default'     => '[]',
                )
            );

            // Full country selector
            $countries = array(
                'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AS' => 'American Samoa',
                'AD' => 'Andorra', 'AO' => 'Angola', 'AI' => 'Anguilla', 'AQ' => 'Antarctica',
                'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia', 'AW' => 'Aruba',
                'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BS' => 'Bahamas',
                'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus',
                'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermuda',
                'BT' => 'Bhutan', 'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana',
                'BR' => 'Brazil', 'BN' => 'Brunei', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso',
                'BI' => 'Burundi', 'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada',
                'CV' => 'Cape Verde', 'KY' => 'Cayman Islands', 'CF' => 'Central African Republic',
                'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China', 'CO' => 'Colombia',
                'KM' => 'Comoros', 'CG' => 'Congo', 'CD' => 'Congo, Democratic Republic',
                'CK' => 'Cook Islands', 'CR' => 'Costa Rica', 'CI' => 'Côte d\'Ivoire', 'HR' => 'Croatia',
                'CU' => 'Cuba', 'CW' => 'Curaçao', 'CY' => 'Cyprus', 'CZ' => 'Czech Republic',
                'DK' => 'Denmark', 'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic',
                'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea',
                'ER' => 'Eritrea', 'EE' => 'Estonia', 'ET' => 'Ethiopia', 'FK' => 'Falkland Islands',
                'FO' => 'Faroe Islands', 'FJ' => 'Fiji', 'FI' => 'Finland', 'FR' => 'France',
                'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany',
                'GH' => 'Ghana', 'GI' => 'Gibraltar', 'GR' => 'Greece', 'GL' => 'Greenland',
                'GD' => 'Grenada', 'GU' => 'Guam', 'GT' => 'Guatemala', 'GG' => 'Guernsey',
                'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HT' => 'Haiti',
                'HN' => 'Honduras', 'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland',
                'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran', 'IQ' => 'Iraq',
                'IE' => 'Ireland', 'IM' => 'Isle of Man', 'IL' => 'Israel', 'IT' => 'Italy',
                'JM' => 'Jamaica', 'JP' => 'Japan', 'JE' => 'Jersey', 'JO' => 'Jordan',
                'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KI' => 'Kiribati', 'KW' => 'Kuwait',
                'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia', 'LB' => 'Lebanon',
                'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya', 'LI' => 'Liechtenstein',
                'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MO' => 'Macau', 'MK' => 'Macedonia',
                'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia', 'MV' => 'Maldives',
                'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands', 'MR' => 'Mauritania',
                'MU' => 'Mauritius', 'MX' => 'Mexico', 'FM' => 'Micronesia', 'MD' => 'Moldova',
                'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro', 'MS' => 'Montserrat',
                'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia',
                'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'NC' => 'New Caledonia',
                'NZ' => 'New Zealand', 'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria',
                'NU' => 'Niue', 'NF' => 'Norfolk Island', 'KP' => 'North Korea', 'MP' => 'Northern Mariana Islands',
                'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan', 'PW' => 'Palau',
                'PS' => 'Palestine', 'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay',
                'PE' => 'Peru', 'PH' => 'Philippines', 'PN' => 'Pitcairn', 'PL' => 'Poland',
                'PT' => 'Portugal', 'PR' => 'Puerto Rico', 'QA' => 'Qatar', 'RO' => 'Romania',
                'RU' => 'Russia', 'RW' => 'Rwanda', 'WS' => 'Samoa', 'SM' => 'San Marino',
                'ST' => 'São Tomé and Príncipe', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal',
                'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore',
                'SX' => 'Sint Maarten', 'SK' => 'Slovakia', 'SI' => 'Slovenia', 'SB' => 'Solomon Islands',
                'SO' => 'Somalia', 'ZA' => 'South Africa', 'KR' => 'South Korea', 'SS' => 'South Sudan',
                'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SD' => 'Sudan', 'SR' => 'Suriname',
                'SZ' => 'Swaziland', 'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syria',
                'TW' => 'Taiwan', 'TJ' => 'Tajikistan', 'TZ' => 'Tanzania', 'TH' => 'Thailand',
                'TL' => 'Timor-Leste', 'TG' => 'Togo', 'TK' => 'Tokelau', 'TO' => 'Tonga',
                'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey', 'TM' => 'Turkmenistan',
                'TC' => 'Turks and Caicos Islands', 'TV' => 'Tuvalu', 'UG' => 'Uganda', 'UA' => 'Ukraine',
                'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States',
                'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VA' => 'Vatican City',
                'VE' => 'Venezuela', 'VN' => 'Vietnam', 'VG' => 'Virgin Islands, British',
                'VI' => 'Virgin Islands, U.S.', 'WF' => 'Wallis and Futuna', 'EH' => 'Western Sahara',
                'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe'
            );

            // Sort countries alphabetically
            asort($countries);

            $options_html = '';
            foreach ($countries as $cc => $nn) {
                $options_html .= '<option value="' . esc_attr($cc) . '">' . esc_html($nn) . '</option>';
            }

            $widget->add_control(
                'egp_geo_countries_native',
                array(
                    'type' => \Elementor\Controls_Manager::RAW_HTML,
                    'raw'  => '<label style="display:block;margin-bottom:8px;font-weight:600;">' . esc_html__('Target Countries', 'elementor-geo-popup') . '</label>'
                           . '<select id="egp_countries_native" name="egp_countries_native[]" multiple="multiple" size="12" style="width:100%;max-width:420px;min-height:200px;border:1px solid #ddd;border-radius:4px;padding:8px;font-size:13px;">' . $options_html . '</select>'
                           . '<p class="description" style="margin-top:8px;color:#666;font-size:12px;">' . esc_html__('Hold Ctrl (Windows) or Cmd (Mac) to select multiple countries.', 'elementor-geo-popup') . '</p>',
                    'content_classes' => 'egp-native-countries',
                    'condition'   => array('egp_geo_enabled' => 'yes'),
                )
            );

            // Move Priority below Target Countries
            $widget->add_control(
                'egp_geo_priority',
                array(
                    'label'       => __('Priority', 'elementor-geo-popup'),
                    'type'        => \Elementor\Controls_Manager::NUMBER,
                    'min'         => 1,
                    'max'         => 100,
                    'step'        => 1,
                    'default'     => 50,
                    'condition'   => array('egp_geo_enabled' => 'yes'),
                    'description' => __('Higher numbers take precedence over lower priority geo rules (1-100).', 'elementor-geo-popup'),
                )
            );

            // Move Tracking ID below Priority
            $widget->add_control(
                'egp_geo_tracking_id',
                array(
                    'label'       => __('Tracking ID', 'elementor-geo-popup'),
                    'type'        => \Elementor\Controls_Manager::TEXT,
                    'condition'   => array('egp_geo_enabled' => 'yes'),
                    'description' => __('Used for analytics and tracking', 'elementor-geo-popup'),
                    'placeholder' => __('Auto-generated if empty', 'elementor-geo-popup'),
                )
            );
        } else {
            $widget->add_control(
                'egp_geo_upgrade',
                array(
                    'type' => \Elementor\Controls_Manager::RAW_HTML,
                    'raw'  => '<div style="background:#fff3cd;border:1px solid #ffeaa7;color:#856404;padding:12px;border-radius:4px;margin-top:10px;">'
                            . '<strong>' . esc_html__('Pro Feature', 'elementor-geo-popup') . '</strong><br>'
                            . esc_html__('Upgrade to Pro to target this widget by country.', 'elementor-geo-popup')
                            . '</div>',
                    'content_classes' => 'egp-upgrade-box',
                )
            );
        }

        $widget->end_controls_section();

        error_log('[EGP] Successfully added geo controls to widget: ' . $widget->get_name());

        // Close injection block if opened
        if (method_exists($widget, 'end_injection')) {
            $widget->end_injection();
        }

        // Add console success message
        add_action('admin_footer', function() use ($widget) {
            $name = $widget->get_name();
            echo '<script>console.log("[EGP] ✅ Geo Targeting panel added to widget: ' . esc_js($name) . '");</script>';
        });
    }

    /**
     * Add Geo Targeting controls to a container using Elementor's API
     */
    private function add_geo_controls_to_container($container) {
        // Check if container already has our controls
        $controls = $container->get_controls();
        if (isset($controls['egp_geo_tools'])) {
            error_log('[EGP] Container already has geo controls');
            return;
        }

        error_log('[EGP] Adding geo controls to container');

        // Inject our section at the START of the Advanced tab for containers
        if (method_exists($container, 'start_injection')) {
            $container->start_injection(array(
                'type' => 'section',
                'at'   => 'start',
                'of'   => 'section_advanced',
            ));
        }

        // Add the controls section at the TOP of Advanced tab
        $container->start_controls_section(
            'egp_geo_tools',
            array(
                'label' => __('Geo Targeting', 'elementor-geo-popup'),
                'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
            ),
            array(
                'priority' => 1, // High priority to show at top
            )
        );

        // If not configured, show setup callout and return
        if (!$this->geo_ready) {
            $settings_url = admin_url('admin.php?page=elementor-geo-popup#maxmind');
            $container->add_control(
                'egp_geo_setup_notice',
                array(
                    'type' => \Elementor\Controls_Manager::RAW_HTML,
                    'raw'  => '<div style="background:#fff8e5;border:1px solid #ffe08a;padding:10px;border-radius:4px;">'
                           . esc_html__('Geo Targeting is inactive until you add your MaxMind license key.', 'elementor-geo-popup')
                           . ' <a href="' . esc_url($settings_url) . '" target="_blank">' . esc_html__('Open Settings', 'elementor-geo-popup') . '</a>'
                           . '</div>',
                )
            );
            $container->end_controls_section();
            if (method_exists($container, 'end_injection')) { $container->end_injection(); }
            return;
        }

        // Add element ID control with auto-generated value
        $container->add_control(
            'egp_element_id',
            array(
                'label'       => __('Element ID', 'elementor-geo-popup'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'description' => __('Unique ID for this container. Used in Rules/Groups for targeting. Leave empty to auto-generate.', 'elementor-geo-popup'),
                'placeholder' => __('Leave empty to auto-generate', 'elementor-geo-popup'),
                'default'     => '',
            )
        );

        // Add Pro controls if user has Pro
        $is_pro = current_user_can('manage_woocommerce') || apply_filters('egp_is_pro_user', false);

        if ($is_pro) {
            $container->add_control(
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

            $container->add_control(
                'egp_geo_countries_store',
                array(
                    'type'        => \Elementor\Controls_Manager::HIDDEN,
                    'condition'   => array('egp_geo_enabled' => 'yes'),
                    'default'     => '[]',
                )
            );

            // Full country selector
            $countries = array(
                'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AS' => 'American Samoa',
                'AD' => 'Andorra', 'AO' => 'Angola', 'AI' => 'Anguilla', 'AQ' => 'Antarctica',
                'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia', 'AW' => 'Aruba',
                'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BS' => 'Bahamas',
                'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus',
                'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermuda',
                'BT' => 'Bhutan', 'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana',
                'BR' => 'Brazil', 'BN' => 'Brunei', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso',
                'BI' => 'Burundi', 'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada',
                'CV' => 'Cape Verde', 'KY' => 'Cayman Islands', 'CF' => 'Central African Republic',
                'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China', 'CO' => 'Colombia',
                'KM' => 'Comoros', 'CG' => 'Congo', 'CD' => 'Congo, Democratic Republic',
                'CK' => 'Cook Islands', 'CR' => 'Costa Rica', 'CI' => 'Côte d\'Ivoire', 'HR' => 'Croatia',
                'CU' => 'Cuba', 'CW' => 'Curaçao', 'CY' => 'Cyprus', 'CZ' => 'Czech Republic',
                'DK' => 'Denmark', 'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic',
                'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea',
                'ER' => 'Eritrea', 'EE' => 'Estonia', 'ET' => 'Ethiopia', 'FK' => 'Falkland Islands',
                'FO' => 'Faroe Islands', 'FJ' => 'Fiji', 'FI' => 'Finland', 'FR' => 'France',
                'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany',
                'GH' => 'Ghana', 'GI' => 'Gibraltar', 'GR' => 'Greece', 'GL' => 'Greenland',
                'GD' => 'Grenada', 'GU' => 'Guam', 'GT' => 'Guatemala', 'GG' => 'Guernsey',
                'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HT' => 'Haiti',
                'HN' => 'Honduras', 'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland',
                'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran', 'IQ' => 'Iraq',
                'IE' => 'Ireland', 'IM' => 'Isle of Man', 'IL' => 'Israel', 'IT' => 'Italy',
                'JM' => 'Jamaica', 'JP' => 'Japan', 'JE' => 'Jersey', 'JO' => 'Jordan',
                'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KI' => 'Kiribati', 'KW' => 'Kuwait',
                'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia', 'LB' => 'Lebanon',
                'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya', 'LI' => 'Liechtenstein',
                'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MO' => 'Macau', 'MK' => 'Macedonia',
                'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia', 'MV' => 'Maldives',
                'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands', 'MR' => 'Mauritania',
                'MU' => 'Mauritius', 'MX' => 'Mexico', 'FM' => 'Micronesia', 'MD' => 'Moldova',
                'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro', 'MS' => 'Montserrat',
                'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia',
                'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'NC' => 'New Caledonia',
                'NZ' => 'New Zealand', 'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria',
                'NU' => 'Niue', 'NF' => 'Norfolk Island', 'KP' => 'North Korea', 'MP' => 'Northern Mariana Islands',
                'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan', 'PW' => 'Palau',
                'PS' => 'Palestine', 'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay',
                'PE' => 'Peru', 'PH' => 'Philippines', 'PN' => 'Pitcairn', 'PL' => 'Poland',
                'PT' => 'Portugal', 'PR' => 'Puerto Rico', 'QA' => 'Qatar', 'RO' => 'Romania',
                'RU' => 'Russia', 'RW' => 'Rwanda', 'WS' => 'Samoa', 'SM' => 'San Marino',
                'ST' => 'São Tomé and Príncipe', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal',
                'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore',
                'SX' => 'Sint Maarten', 'SK' => 'Slovakia', 'SI' => 'Slovenia', 'SB' => 'Solomon Islands',
                'SO' => 'Somalia', 'ZA' => 'South Africa', 'KR' => 'South Korea', 'SS' => 'South Sudan',
                'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SD' => 'Sudan', 'SR' => 'Suriname',
                'SZ' => 'Swaziland', 'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syria',
                'TW' => 'Taiwan', 'TJ' => 'Tajikistan', 'TZ' => 'Tanzania', 'TH' => 'Thailand',
                'TL' => 'Timor-Leste', 'TG' => 'Togo', 'TK' => 'Tokelau', 'TO' => 'Tonga',
                'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey', 'TM' => 'Turkmenistan',
                'TC' => 'Turks and Caicos Islands', 'TV' => 'Tuvalu', 'UG' => 'Uganda', 'UA' => 'Ukraine',
                'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States',
                'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VA' => 'Vatican City',
                'VE' => 'Venezuela', 'VN' => 'Vietnam', 'VG' => 'Virgin Islands, British',
                'VI' => 'Virgin Islands, U.S.', 'WF' => 'Wallis and Futuna', 'EH' => 'Western Sahara',
                'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe'
            );

            // Sort countries alphabetically
            asort($countries);

            $options_html = '';
            foreach ($countries as $cc => $nn) {
                $options_html .= '<option value="' . esc_attr($cc) . '">' . esc_html($nn) . '</option>';
            }

            $container->add_control(
                'egp_geo_countries_native',
                array(
                    'type' => \Elementor\Controls_Manager::RAW_HTML,
                    'raw'  => '<label style="display:block;margin-bottom:8px;font-weight:600;">' . esc_html__('Target Countries', 'elementor-geo-popup') . '</label>'
                           . '<select id="egp_countries_native" name="egp_countries_native[]" multiple="multiple" size="12" style="width:100%;max-width:420px;min-height:200px;border:1px solid #ddd;border-radius:4px;padding:8px;font-size:13px;">' . $options_html . '</select>'
                           . '<p class="description" style="margin-top:8px;color:#666;font-size:12px;">' . esc_html__('Hold Ctrl (Windows) or Cmd (Mac) to select multiple countries.', 'elementor-geo-popup') . '</p>',
                    'content_classes' => 'egp-native-countries',
                    'condition'   => array('egp_geo_enabled' => 'yes'),
                )
            );

            // Priority with tooltip
            $container->add_control(
                'egp_geo_priority',
                array(
                    'label'       => __('Priority', 'elementor-geo-popup'),
                    'type'        => \Elementor\Controls_Manager::NUMBER,
                    'min'         => 1,
                    'max'         => 100,
                    'step'        => 1,
                    'default'     => 50,
                    'condition'   => array('egp_geo_enabled' => 'yes'),
                    'description' => __('Higher numbers take precedence over lower priority geo rules (1-100).', 'elementor-geo-popup'),
                )
            );

            // Tracking ID
            $container->add_control(
                'egp_geo_tracking_id',
                array(
                    'label'       => __('Tracking ID', 'elementor-geo-popup'),
                    'type'        => \Elementor\Controls_Manager::TEXT,
                    'condition'   => array('egp_geo_enabled' => 'yes'),
                    'description' => __('Used for analytics and tracking', 'elementor-geo-popup'),
                    'placeholder' => __('Auto-generated if empty', 'elementor-geo-popup'),
                )
            );
        } else {
            $container->add_control(
                'egp_geo_upgrade',
                array(
                    'type' => \Elementor\Controls_Manager::RAW_HTML,
                    'raw'  => '<div style="background:#fff3cd;border:1px solid #ffeaa7;color:#856404;padding:12px;border-radius:4px;margin-top:10px;">'
                            . '<strong>' . esc_html__('Pro Feature', 'elementor-geo-popup') . '</strong><br>'
                            . esc_html__('Upgrade to Pro to target this container by country.', 'elementor-geo-popup')
                            . '</div>',
                    'content_classes' => 'egp-upgrade-box',
                )
            );
        }

        $container->end_controls_section();

        error_log('[EGP] Successfully added geo controls to container');

        // Close injection block if opened
        if (method_exists($container, 'end_injection')) {
            $container->end_injection();
        }

        // Add console success message
        add_action('admin_footer', function() {
            echo '<script>console.log("[EGP] ✅ Geo Targeting panel added to container");</script>';
        });
    }

    /**
     * Add Geo Targeting controls to an Elementor element (legacy method)
     */
    private function add_geo_targeting_controls($element) {
        // Determine Pro gating (same heuristic as elsewhere)
        $is_pro = current_user_can('manage_woocommerce') || apply_filters('egp_is_pro_user', false);

        // Add debug logging
        $element_name = method_exists($element, 'get_name') ? $element->get_name() : 'unknown';
        $element_type = method_exists($element, 'get_type') ? $element->get_type() : 'unknown';
        error_log('[EGP] Adding geo controls to element: ' . $element_name . ' (type: ' . $element_type . ')');

        // Check if geo controls already exist to prevent duplicates
        $controls = $element->get_controls();
        if (is_array($controls) && isset($controls['egp_geo_tools'])) {
            error_log('[EGP] Geo controls already exist for element: ' . $element_name . ' - skipping duplicate');
            return;
        }

        // Add inline script for immediate console feedback
        add_action('admin_footer', function() use ($element_name, $element_type) {
            echo '<script>console.log("[EGP] Adding geo controls to element: ' . esc_js($element_name) . ' (type: ' . esc_js($element_type) . ')");</script>';
        });

        $element->start_controls_section(
            'egp_geo_tools',
            array(
                'label' => __('Geo Targeting', 'elementor-geo-popup'),
                'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
            )
        );

        // Add element ID display for targeting reference
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

            // Store countries as JSON in hidden field
            $element->add_control(
                'egp_geo_countries_store',
                array(
                    'type'        => \Elementor\Controls_Manager::HIDDEN,
                    'condition'   => array('egp_geo_enabled' => 'yes'),
                    'default'     => '[]',
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

            // Country selector using full country list
            $countries = array(
                'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AS' => 'American Samoa',
                'AD' => 'Andorra', 'AO' => 'Angola', 'AI' => 'Anguilla', 'AQ' => 'Antarctica',
                'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia', 'AW' => 'Aruba',
                'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BS' => 'Bahamas',
                'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus',
                'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermuda',
                'BT' => 'Bhutan', 'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana',
                'BR' => 'Brazil', 'BN' => 'Brunei', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso',
                'BI' => 'Burundi', 'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada',
                'CV' => 'Cape Verde', 'KY' => 'Cayman Islands', 'CF' => 'Central African Republic',
                'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China', 'CO' => 'Colombia',
                'KM' => 'Comoros', 'CG' => 'Congo', 'CD' => 'Congo, Democratic Republic',
                'CK' => 'Cook Islands', 'CR' => 'Costa Rica', 'CI' => 'Côte d\'Ivoire', 'HR' => 'Croatia',
                'CU' => 'Cuba', 'CW' => 'Curaçao', 'CY' => 'Cyprus', 'CZ' => 'Czech Republic',
                'DK' => 'Denmark', 'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic',
                'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea',
                'ER' => 'Eritrea', 'EE' => 'Estonia', 'ET' => 'Ethiopia', 'FK' => 'Falkland Islands',
                'FO' => 'Faroe Islands', 'FJ' => 'Fiji', 'FI' => 'Finland', 'FR' => 'France',
                'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany',
                'GH' => 'Ghana', 'GI' => 'Gibraltar', 'GR' => 'Greece', 'GL' => 'Greenland',
                'GD' => 'Grenada', 'GU' => 'Guam', 'GT' => 'Guatemala', 'GG' => 'Guernsey',
                'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HT' => 'Haiti',
                'HN' => 'Honduras', 'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland',
                'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran', 'IQ' => 'Iraq',
                'IE' => 'Ireland', 'IM' => 'Isle of Man', 'IL' => 'Israel', 'IT' => 'Italy',
                'JM' => 'Jamaica', 'JP' => 'Japan', 'JE' => 'Jersey', 'JO' => 'Jordan',
                'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KI' => 'Kiribati', 'KW' => 'Kuwait',
                'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia', 'LB' => 'Lebanon',
                'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya', 'LI' => 'Liechtenstein',
                'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MO' => 'Macau', 'MK' => 'Macedonia',
                'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia', 'MV' => 'Maldives',
                'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands', 'MR' => 'Mauritania',
                'MU' => 'Mauritius', 'MX' => 'Mexico', 'FM' => 'Micronesia', 'MD' => 'Moldova',
                'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro', 'MS' => 'Montserrat',
                'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia',
                'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'NC' => 'New Caledonia',
                'NZ' => 'New Zealand', 'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria',
                'NU' => 'Niue', 'NF' => 'Norfolk Island', 'KP' => 'North Korea', 'MP' => 'Northern Mariana Islands',
                'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan', 'PW' => 'Palau',
                'PS' => 'Palestine', 'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay',
                'PE' => 'Peru', 'PH' => 'Philippines', 'PN' => 'Pitcairn', 'PL' => 'Poland',
                'PT' => 'Portugal', 'PR' => 'Puerto Rico', 'QA' => 'Qatar', 'RO' => 'Romania',
                'RU' => 'Russia', 'RW' => 'Rwanda', 'WS' => 'Samoa', 'SM' => 'San Marino',
                'ST' => 'São Tomé and Príncipe', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal',
                'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore',
                'SX' => 'Sint Maarten', 'SK' => 'Slovakia', 'SI' => 'Slovenia', 'SB' => 'Solomon Islands',
                'SO' => 'Somalia', 'ZA' => 'South Africa', 'KR' => 'South Korea', 'SS' => 'South Sudan',
                'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SD' => 'Sudan', 'SR' => 'Suriname',
                'SZ' => 'Swaziland', 'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syria',
                'TW' => 'Taiwan', 'TJ' => 'Tajikistan', 'TZ' => 'Tanzania', 'TH' => 'Thailand',
                'TL' => 'Timor-Leste', 'TG' => 'Togo', 'TK' => 'Tokelau', 'TO' => 'Tonga',
                'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey', 'TM' => 'Turkmenistan',
                'TC' => 'Turks and Caicos Islands', 'TV' => 'Tuvalu', 'UG' => 'Uganda', 'UA' => 'Ukraine',
                'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States',
                'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VA' => 'Vatican City',
                'VE' => 'Venezuela', 'VN' => 'Vietnam', 'VG' => 'Virgin Islands, British',
                'VI' => 'Virgin Islands, U.S.', 'WF' => 'Wallis and Futuna', 'EH' => 'Western Sahara',
                'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe'
            );

            // Sort countries alphabetically
            asort($countries);

            $options_html = '';
            foreach ($countries as $cc => $nn) {
                $options_html .= '<option value="' . esc_attr($cc) . '">' . esc_html($nn) . '</option>';
            }

            $element->add_control(
                'egp_geo_countries_native',
                array(
                    'type' => \Elementor\Controls_Manager::RAW_HTML,
                    'raw'  => '<label style="display:block;margin-bottom:8px;font-weight:600;">' . esc_html__('Target Countries', 'elementor-geo-popup') . '</label>'
                           . '<select id="egp_countries_native" name="egp_countries_native[]" multiple="multiple" size="12" style="width:100%;max-width:420px;min-height:200px;border:1px solid #ddd;border-radius:4px;padding:8px;font-size:13px;">' . $options_html . '</select>'
                           . '<p class="description" style="margin-top:8px;color:#666;font-size:12px;">' . esc_html__('Hold Ctrl/Cmd to select multiple countries. Countries are stored as JSON.', 'elementor-geo-popup') . '</p>',
                    'content_classes' => 'egp-native-countries',
                    'condition'   => array('egp_geo_enabled' => 'yes'),
                )
            );
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

        error_log('[EGP] Geo controls added successfully to element: ' . $element_name);

        // Add success message to console
        add_action('admin_footer', function() use ($element_name) {
            echo '<script>console.log("[EGP] ✅ Geo Targeting panel added to ' . esc_js($element_name) . '");</script>';
        });
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
}

// Initialize the plugin
ElementorGeoPopup::get_instance();

