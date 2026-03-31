<?php
/**
 * Global Settings Integration
 *
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Global Settings Integration Class
 * 
 * Integrates geo-targeting options into Elementor's global settings system
 */
class EGP_Global_Settings {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('elementor/init', [$this, 'init_global_settings']);
        add_action('elementor/editor/before_enqueue_scripts', [$this, 'enqueue_global_scripts']);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueue_global_styles']);
        add_filter('elementor/editor/localize_settings', [$this, 'add_global_settings_data']);
    }

    /**
     * Initialize global settings
     */
    public function init_global_settings() {
        // Add geo-targeting controls to global settings
        add_action('elementor/element/global-widget/section_global_widget/before_section_end', [$this, 'add_geo_controls_to_global_widget']);
        add_action('elementor/element/global-widget/section_global_widget/before_section_end', [$this, 'add_geo_controls_to_global_widget']);
        
        // Add geo-targeting to global colors, typography, etc.
        add_action('elementor/element/global-colors/section_global_colors/before_section_end', [$this, 'add_geo_controls_to_global_colors']);
        add_action('elementor/element/global-typography/section_global_typography/before_section_end', [$this, 'add_geo_controls_to_global_typography']);
    }

    /**
     * Add geo controls to global widget
     */
    public function add_geo_controls_to_global_widget($element) {
        $element->add_control(
            'egp_geo_targeting_enabled',
            [
                'label' => esc_html__('Enable Geo Targeting', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'elementor-geo-popup'),
                'label_off' => esc_html__('No', 'elementor-geo-popup'),
                'return_value' => 'yes',
                'default' => 'no',
                'separator' => 'before',
            ]
        );

        $element->add_control(
            'egp_target_countries',
            [
                'label' => esc_html__('Target Countries', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'multiple' => true,
                'label_block' => true,
                'options' => $this->get_countries_list(),
                'default' => $this->get_preferred_countries(),
                'render_type' => 'none',
                'condition' => [
                    'egp_geo_targeting_enabled' => 'yes',
                ],
            ]
        );

        $element->add_control(
            'egp_fallback_behavior',
            [
                'label' => esc_html__('Fallback Behavior', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'hide',
                'options' => [
                    'hide' => esc_html__('Hide for non-matching countries', 'elementor-geo-popup'),
                    'show' => esc_html__('Show for all countries', 'elementor-geo-popup'),
                    'default' => esc_html__('Use default global value', 'elementor-geo-popup'),
                ],
                'condition' => [
                    'egp_geo_targeting_enabled' => 'yes',
                ],
            ]
        );
    }

    /**
     * Add geo controls to global colors
     */
    public function add_geo_controls_to_global_colors($element) {
        $element->add_control(
            'egp_geo_targeting_enabled',
            [
                'label' => esc_html__('Enable Geo Targeting', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'elementor-geo-popup'),
                'label_off' => esc_html__('No', 'elementor-geo-popup'),
                'return_value' => 'yes',
                'default' => 'no',
                'separator' => 'before',
            ]
        );

        $element->add_control(
            'egp_target_countries',
            [
                'label' => esc_html__('Target Countries', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'multiple' => true,
                'label_block' => true,
                'options' => $this->get_countries_list(),
                'default' => $this->get_preferred_countries(),
                'render_type' => 'none',
                'condition' => [
                    'egp_geo_targeting_enabled' => 'yes',
                ],
            ]
        );

        $element->add_control(
            'egp_alternative_color',
            [
                'label' => esc_html__('Alternative Color', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'condition' => [
                    'egp_geo_targeting_enabled' => 'yes',
                ],
            ]
        );
    }

    /**
     * Add geo controls to global typography
     */
    public function add_geo_controls_to_global_typography($element) {
        $element->add_control(
            'egp_geo_targeting_enabled',
            [
                'label' => esc_html__('Enable Geo Targeting', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'elementor-geo-popup'),
                'label_off' => esc_html__('No', 'elementor-geo-popup'),
                'return_value' => 'yes',
                'default' => 'no',
                'separator' => 'before',
            ]
        );

        $element->add_control(
            'egp_target_countries',
            [
                'label' => esc_html__('Target Countries', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'multiple' => true,
                'label_block' => true,
                'options' => $this->get_countries_list(),
                'default' => $this->get_preferred_countries(),
                'render_type' => 'none',
                'condition' => [
                    'egp_geo_targeting_enabled' => 'yes',
                ],
            ]
        );

        $element->add_control(
            'egp_alternative_font_family',
            [
                'label' => esc_html__('Alternative Font Family', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::FONT,
                'condition' => [
                    'egp_geo_targeting_enabled' => 'yes',
                ],
            ]
        );

        $element->add_control(
            'egp_alternative_font_size',
            [
                'label' => esc_html__('Alternative Font Size', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem', '%'],
                'range' => [
                    'px' => [
                        'min' => 1,
                        'max' => 200,
                    ],
                    'em' => [
                        'min' => 0.1,
                        'max' => 20,
                    ],
                    'rem' => [
                        'min' => 0.1,
                        'max' => 20,
                    ],
                    '%' => [
                        'min' => 0.1,
                        'max' => 200,
                    ],
                ],
                'condition' => [
                    'egp_geo_targeting_enabled' => 'yes',
                ],
            ]
        );
    }

    /**
     * Enqueue global scripts
     */
    public function enqueue_global_scripts() {
        // Only enqueue if file is readable and response is JS (avoid 404/HTML causing SyntaxError in console)
        $script_url = EGP_PLUGIN_URL . 'assets/js/global-settings.js';
        $script_path = EGP_PLUGIN_DIR . 'assets/js/global-settings.js';
        if (!file_exists($script_path)) {
            return;
        }
        wp_enqueue_script(
            'egp-global-settings',
            $script_url,
            ['jquery', 'elementor-editor'],
            EGP_VERSION,
            true
        );

        wp_localize_script('egp-global-settings', 'egpGlobalSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('egp_global_settings_nonce'),
            'strings' => [
                'geoTargeting' => esc_html__('Geo Targeting', 'elementor-geo-popup'),
                'enableGeo' => esc_html__('Enable Geo Targeting', 'elementor-geo-popup'),
                'targetCountries' => esc_html__('Target Countries', 'elementor-geo-popup'),
                'fallbackBehavior' => esc_html__('Fallback Behavior', 'elementor-geo-popup'),
            ],
        ]);
    }

    /**
     * Enqueue global styles
     */
    public function enqueue_global_styles() {
        wp_enqueue_style(
            'egp-global-settings',
            EGP_PLUGIN_URL . 'assets/css/global-settings.css',
            [],
            EGP_VERSION
        );
    }

    /**
     * Add global settings data to Elementor editor
     */
    public function add_global_settings_data($settings) {
        $settings['egp_global_settings'] = [
            'countries' => $this->get_countries_list(),
            'default_countries' => get_option('egp_preferred_countries', ['US', 'CA', 'GB']),
            'geo_targeting_enabled' => get_option('egp_global_geo_targeting', 'no'),
        ];

        return $settings;
    }

    /**
     * Get preferred countries from admin settings
     */
    private function get_preferred_countries() {
        return get_option('egp_preferred_countries', ['US', 'CA', 'GB']);
    }

    /**
     * Get countries list (canonical: egp_get_country_options).
     */
    private function get_countries_list() {
        return function_exists( 'egp_get_country_options' ) ? egp_get_country_options() : array();
    }
}
