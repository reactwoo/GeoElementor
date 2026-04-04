<?php
/**
 * Geo Container Widget
 * 
 * Elementor widget to insert geo-targeted container templates
 * 
 * @package GeoElementor
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class EGP_Geo_Container_Widget extends \Elementor\Widget_Base {
    
    public function get_name() {
        return 'geo-container';
    }
    
    public function get_title() {
        return __('Geo Container', 'elementor-geo-popup');
    }
    
    public function get_icon() {
        return 'eicon-container';
    }
    
    public function get_categories() {
        return ['general'];
    }
    
    public function get_keywords() {
        return ['geo', 'location', 'country', 'container', 'template'];
    }
    
    public function get_style_depends() {
        return ['egp-geo-widgets'];
    }
    
    public function __construct($data = [], $args = null) {
        parent::__construct($data, $args);
        
        wp_register_style(
            'egp-geo-widgets',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/geo-widgets.css',
            [],
            '1.0.0'
        );
    }
    
    protected function register_controls() {
        
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Template', 'elementor-geo-popup'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'template_id',
            [
                'label' => __('Select Template', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $this->get_template_options(),
                'default' => '',
                'description' => __('Select a geo container template', 'elementor-geo-popup'),
            ]
        );
        
        $this->add_control(
            'template_preview',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => '<div class="elementor-control-raw-html elementor-panel-alert elementor-panel-alert-info">' .
                         __('Container will be displayed based on visitor country.', 'elementor-geo-popup') .
                         '</div>',
            ]
        );
        
        $this->end_controls_section();
        
        // Override Section
        $this->start_controls_section(
            'override_section',
            [
                'label' => __('Override Settings', 'elementor-geo-popup'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        // Use native HTML select instead of SELECT2
        $this->add_control(
            'override_countries_html',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => $this->get_countries_select_html('override_countries', __('Override Countries', 'elementor-geo-popup')),
            ]
        );
        
        $this->add_control(
            'override_countries',
            [
                'type' => \Elementor\Controls_Manager::HIDDEN,
                'default' => '',
            ]
        );
        
        $this->add_control(
            'show_in_editor',
            [
                'label' => __('Show in Editor', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->end_controls_section();
    }
    
    protected function render() {
        $settings = $this->get_settings_for_display();
        $template_id = $settings['template_id'];
        
        if (empty($template_id)) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="elementor-alert elementor-alert-warning">';
                echo __('Please select a geo container template', 'elementor-geo-popup');
                echo '</div>';
            }
            return;
        }
        
        $template = get_post($template_id);
        if (!$template) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="elementor-alert elementor-alert-danger">';
                echo __('Selected template not found', 'elementor-geo-popup');
                echo '</div>';
            }
            return;
        }
        
        // Get user's country (Geo Core + EGP detect via egp_get_visitor_country_for_targeting)
        $user_country = egp_get_visitor_country_for_targeting();
        if ((false === $user_country || '' === $user_country) && ! class_exists('EGP_Geo_Detect') && ! (function_exists('rwgc_is_ready') && rwgc_is_ready())) {
            $user_country = 'US';
        }
        
        // Get countries
        $countries = $settings['override_countries'];
        if (empty($countries)) {
            $countries = get_post_meta($template_id, 'egp_countries', true);
        }
        
        $is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode();
        $bypass_geo = class_exists('EGP_Editor_Context', false) && EGP_Editor_Context::should_bypass_geo_rules();
        if ($bypass_geo) {
            $should_show = true;
            $is_editor = true;
        } else {
            $should_show = ($is_editor && $settings['show_in_editor'] === 'yes') ||
                           (!$is_editor && in_array($user_country, (array)$countries));
        }
        
        if (!$should_show) {
            return;
        }
        
        echo '<div class="egp-geo-container" data-template-id="' . esc_attr($template_id) . '">';
        
        if ($is_editor) {
            echo '<div class="egp-template-badge">';
            echo '<span class="egp-template-name">📦 ' . esc_html($template->post_title) . '</span>';
            echo '<span class="egp-template-countries">(' . esc_html(implode(', ', (array)$countries)) . ')</span>';
            echo '</div>';
        }
        
        echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($template_id);
        echo '</div>';
    }
    
    protected function get_template_options() {
        $options = ['' => __('Select a template', 'elementor-geo-popup')];
        
        $templates = get_posts([
            'post_type' => 'geo_template',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        
        foreach ($templates as $template) {
            $type = get_post_meta($template->ID, 'egp_template_type', true);
            if ($type === 'container' || empty($type)) {
                $options[$template->ID] = $template->post_title;
            }
        }
        
        return $options;
    }
    
    protected function get_countries_list() {
        return function_exists( 'egp_get_country_options' ) ? egp_get_country_options() : array();
    }
    
    protected function get_countries_select_html($control_id, $label) {
        $countries = $this->get_countries_list();
        $html = '<div class="egp-countries-native">';
        $html .= '<label class="elementor-control-title">' . esc_html($label) . '</label>';
        $html .= '<div class="elementor-control-input-wrapper">';
        $html .= '<select id="' . esc_attr($control_id) . '_native" class="egp-country-select" multiple size="8" style="width:100%;max-width:100%;min-height:180px;">';
        foreach ($countries as $code => $name) {
            $html .= '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
        }
        $html .= '</select>';
        $html .= '<p class="description">' . esc_html__('Hold Ctrl/Cmd to select multiple countries.', 'elementor-geo-popup') . '</p>';
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
}

