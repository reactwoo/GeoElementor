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
        
        $document->add_control(
            'egp_countries',
            [
                'label' => __('Target Countries', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => $this->get_countries_list(),
                'label_block' => true,
                'condition' => [
                    'egp_geo_enabled' => 'yes',
                ],
                'description' => __('Template will only display to visitors from these countries', 'elementor-geo-popup'),
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
     * Get countries list
     */
    private function get_countries_list() {
        // Try to load from JSON file
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
        
        // Fallback list
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
}

// Initialize
EGP_Elementor_Template_Integration::get_instance();

