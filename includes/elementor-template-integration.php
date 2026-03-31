<?php
/**
 * Native Elementor Template Integration
 * 
 * Adds geo-targeting capabilities to Elementor's native template system
 * instead of creating a parallel template system
 * 
 * @package GeoElementor
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class EGP_Elementor_Template_Integration {
    
    private static $instance = null;
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
        // Add geo controls to Elementor template editor
        add_action('elementor/documents/register_controls', array($this, 'add_geo_controls_to_template'), 10, 1);
        
        // Add "Geo" category to Elementor library
        add_filter('elementor/template-library/sources/local/get_items', array($this, 'add_geo_category_to_templates'), 10, 1);
        
        // Frontend rendering - check geo targeting when template is used
        add_filter('elementor/frontend/builder_content_data', array($this, 'filter_template_by_geo'), 10, 2);
        
        // Save template settings
        add_action('elementor/document/after_save', array($this, 'save_geo_settings'), 10, 2);
        
        error_log('[EGP] Elementor Template Integration initialized');
    }
    
    /**
     * Add geo targeting controls to Elementor template settings
     */
    public function add_geo_controls_to_template($document) {
        // Only add to library templates (not regular pages)
        if (!$document || $document->get_main_post()->post_type !== 'elementor_library') {
            return;
        }
        
        // Don't add to popups - they get geo controls in Advanced tab instead
        if (method_exists($document, 'get_name') && $document->get_name() === 'popup') {
            return;
        }
        
        error_log('[EGP] Adding geo controls to template: ' . $document->get_main_id());
        
        // Add section in Settings tab
        $document->start_controls_section(
            'egp_geo_targeting_section',
            [
                'label' => __('Geo Targeting', 'elementor-geo-popup'),
                'tab' => \Elementor\Controls_Manager::TAB_SETTINGS,
            ]
        );
        
        $document->add_control(
            'egp_geo_enabled',
            [
                'label' => __('Enable Geo Targeting', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'elementor-geo-popup'),
                'label_off' => __('No', 'elementor-geo-popup'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Make this template country-specific', 'elementor-geo-popup'),
            ]
        );
        
        // Use native HTML select instead of SELECT2
        $document->add_control(
            'egp_countries_html',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => $this->get_countries_select_html(),
                'condition' => [
                    'egp_geo_enabled' => 'yes',
                ],
            ]
        );
        
        $document->add_control(
            'egp_countries',
            [
                'type' => \Elementor\Controls_Manager::HIDDEN,
                'default' => '',
                'condition' => [
                    'egp_geo_enabled' => 'yes',
                ],
            ]
        );
        
        $document->add_control(
            'egp_fallback_mode',
            [
                'label' => __('Fallback for Other Countries', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'hide' => __('Hide (show nothing)', 'elementor-geo-popup'),
                    'show_default' => __('Show default message', 'elementor-geo-popup'),
                ],
                'default' => 'hide',
                'condition' => [
                    'egp_geo_enabled' => 'yes',
                ],
            ]
        );
        
        $document->add_control(
            'egp_template_info',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => '<div class="elementor-panel-alert elementor-panel-alert-info">' .
                         __('This template will appear in the "Geo" category of your Template Library for easy access.', 'elementor-geo-popup') .
                         '</div>',
                'condition' => [
                    'egp_geo_enabled' => 'yes',
                ],
            ]
        );
        
        $document->end_controls_section();
    }
    
    /**
     * Save geo settings when template is saved
     */
    public function save_geo_settings($document, $data) {
        $post_id = $document->get_main_id();
        $settings = $document->get_settings();
        
        // Save our tracking meta
        if (isset($settings['egp_geo_enabled']) && $settings['egp_geo_enabled'] === 'yes') {
            update_post_meta($post_id, $this->meta_prefix . 'geo_enabled', 'yes');
            
            if (isset($settings['egp_countries'])) {
                update_post_meta($post_id, $this->meta_prefix . 'countries', $settings['egp_countries']);
            }
            
            if (isset($settings['egp_fallback_mode'])) {
                update_post_meta($post_id, $this->meta_prefix . 'fallback_mode', $settings['egp_fallback_mode']);
            }
            
            // Track for analytics
            update_post_meta($post_id, $this->meta_prefix . 'is_geo_template', 'yes');
            
            error_log('[EGP] Saved geo settings for template: ' . $post_id);
        } else {
            // Disabled - remove meta
            delete_post_meta($post_id, $this->meta_prefix . 'geo_enabled');
            delete_post_meta($post_id, $this->meta_prefix . 'is_geo_template');
        }
    }
    
    /**
     * Add geo metadata to template items for library filtering
     */
    public function add_geo_category_to_templates($items) {
        if (!is_array($items)) {
            return $items;
        }
        
        foreach ($items as &$item) {
            // Check if template has geo enabled
            $template_id = $item['template_id'];
            $geo_enabled = get_post_meta($template_id, $this->meta_prefix . 'geo_enabled', true);
            
            if ($geo_enabled === 'yes') {
                // Add "geo" to categories
                if (!isset($item['categories'])) {
                    $item['categories'] = [];
                }
                $item['categories'][] = 'geo';
                
                // Add geo info to item
                $item['geo_enabled'] = true;
                $item['geo_countries'] = get_post_meta($template_id, $this->meta_prefix . 'countries', true);
            }
        }
        
        return $items;
    }
    
    /**
     * Filter template rendering based on geo targeting
     */
    public function filter_template_by_geo($data, $post_id) {
        // Check if this template has geo targeting
        $geo_enabled = get_post_meta($post_id, $this->meta_prefix . 'geo_enabled', true);
        
        if ($geo_enabled !== 'yes') {
            return $data; // No geo targeting, show normally
        }
        
        // Get user's country
        $user_country = 'US'; // Default
        if (class_exists('EGP_Geo_Detect')) {
            $geo_detect = EGP_Geo_Detect::get_instance();
            $user_country = $geo_detect->get_visitor_country();
        }
        
        // Get target countries
        $countries = get_post_meta($post_id, $this->meta_prefix . 'countries', true);
        if (!is_array($countries)) {
            $countries = [];
        }
        
        // Check if user's country is in target list
        if (!in_array($user_country, $countries)) {
            // User not in target countries
            error_log('[EGP] Template ' . $post_id . ' blocked for country: ' . $user_country);
            
            // Get fallback mode
            $fallback = get_post_meta($post_id, $this->meta_prefix . 'fallback_mode', true);
            
            if ($fallback === 'show_default') {
                // Return empty data with fallback message
                return []; // Elementor will show nothing, which is what we want
            }
            
            // Return empty data (hide template)
            return [];
        }
        
        // User is in target countries - show template
        error_log('[EGP] Template ' . $post_id . ' allowed for country: ' . $user_country);
        return $data;
    }
    
    /**
     * Get countries select HTML (native select, not SELECT2)
     */
    private function get_countries_select_html() {
        $countries = $this->get_countries_list();
        $html = '<div class="egp-countries-native">';
        $html .= '<label class="elementor-control-title">' . esc_html__('Target Countries', 'elementor-geo-popup') . '</label>';
        $html .= '<div class="elementor-control-input-wrapper">';
        $html .= '<select id="egp_countries_native" class="egp-country-select" multiple size="12" style="width:100%;max-width:100%;min-height:220px;">';
        foreach ($countries as $code => $name) {
            $html .= '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
        }
        $html .= '</select>';
        $html .= '<p class="description">' . esc_html__('Hold Ctrl/Cmd to select multiple countries. Template will only display to visitors from these countries.', 'elementor-geo-popup') . '</p>';
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Get countries list (canonical: egp_get_country_options).
     */
    private function get_countries_list() {
        return function_exists( 'egp_get_country_options' ) ? egp_get_country_options() : array();
    }
}

// Initialize
EGP_Elementor_Template_Integration::get_instance();

