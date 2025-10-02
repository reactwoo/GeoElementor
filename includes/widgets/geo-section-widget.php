<?php
/**
 * Geo Section Widget
 * 
 * Elementor widget to insert geo-targeted template sections
 * 
 * @package GeoElementor
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class EGP_Geo_Section_Widget extends \Elementor\Widget_Base {
    
    /**
     * Get widget name
     */
    public function get_name() {
        return 'geo-section';
    }
    
    /**
     * Get widget title
     */
    public function get_title() {
        return __('Geo Section', 'elementor-geo-popup');
    }
    
    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-section';
    }
    
    /**
     * Get widget categories
     */
    public function get_categories() {
        return ['general'];
    }
    
    /**
     * Get widget keywords
     */
    public function get_keywords() {
        return ['geo', 'location', 'country', 'section', 'template'];
    }
    
    /**
     * Get widget style dependencies
     */
    public function get_style_depends() {
        return ['egp-geo-widgets'];
    }
    
    /**
     * Register widget styles
     */
    public function __construct($data = [], $args = null) {
        parent::__construct($data, $args);
        
        wp_register_style(
            'egp-geo-widgets',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/geo-widgets.css',
            [],
            '1.0.0'
        );
    }
    
    /**
     * Register widget controls
     */
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
                'description' => __('Select a geo template to display', 'elementor-geo-popup'),
            ]
        );
        
        $this->add_control(
            'template_preview',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => '<div class="elementor-control-raw-html elementor-panel-alert elementor-panel-alert-info">' .
                         __('The selected template will be displayed based on the visitor\'s country.', 'elementor-geo-popup') .
                         '</div>',
            ]
        );
        
        $this->add_control(
            'create_new_template',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => '<a href="' . admin_url('admin.php?page=geo-templates') . '" target="_blank" class="elementor-button elementor-button-default">' .
                         __('Create New Template', 'elementor-geo-popup') .
                         '</a>',
                'content_classes' => 'elementor-descriptor',
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
        
        $this->add_control(
            'override_countries',
            [
                'label' => __('Override Countries', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => $this->get_countries_list(),
                'description' => __('Leave empty to use template countries, or override them here', 'elementor-geo-popup'),
            ]
        );
        
        $this->add_control(
            'show_in_editor',
            [
                'label' => __('Show in Editor', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'elementor-geo-popup'),
                'label_off' => __('No', 'elementor-geo-popup'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Show template content in Elementor editor (helpful for design)', 'elementor-geo-popup'),
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section
        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Container Style', 'elementor-geo-popup'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'container_class',
            [
                'label' => __('CSS Classes', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'description' => __('Add custom CSS classes to the container', 'elementor-geo-popup'),
            ]
        );
        
        $this->end_controls_section();
    }
    
    /**
     * Render widget output on the frontend
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        $template_id = $settings['template_id'];
        
        if (empty($template_id)) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="elementor-alert elementor-alert-warning">';
                echo __('Please select a geo template', 'elementor-geo-popup');
                echo '</div>';
            }
            return;
        }
        
        // Get template
        $template = get_post($template_id);
        if (!$template) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="elementor-alert elementor-alert-danger">';
                echo __('Selected template not found', 'elementor-geo-popup');
                echo '</div>';
            }
            return;
        }
        
        // Increment usage count
        $usage_count = get_post_meta($template_id, 'egp_usage_count', true);
        update_post_meta($template_id, 'egp_usage_count', intval($usage_count) + 1);
        
        // Get user's country
        $user_country = 'US'; // Default
        if (class_exists('EGP_Geo_Detect')) {
            $geo_detect = EGP_Geo_Detect::get_instance();
            $user_country = $geo_detect->get_visitor_country();
        }
        
        // Get target countries (override or template)
        $countries = $settings['override_countries'];
        if (empty($countries)) {
            $countries = get_post_meta($template_id, 'egp_countries', true);
        }
        
        // Check if editor mode
        $is_editor = \Elementor\Plugin::$instance->editor->is_edit_mode();
        
        // Should we show the content?
        $should_show = false;
        
        if ($is_editor && $settings['show_in_editor'] === 'yes') {
            // Always show in editor if enabled
            $should_show = true;
        } elseif (!$is_editor && in_array($user_country, (array)$countries)) {
            // Show on frontend if country matches
            $should_show = true;
        }
        
        if (!$should_show) {
            // Render fallback
            $this->render_fallback($template_id);
            return;
        }
        
        // Render template content
        $container_classes = ['egp-geo-section'];
        if (!empty($settings['container_class'])) {
            $container_classes[] = $settings['container_class'];
        }
        
        echo '<div class="' . esc_attr(implode(' ', $container_classes)) . '" data-template-id="' . esc_attr($template_id) . '">';
        
        if ($is_editor) {
            echo '<div class="egp-template-badge">';
            echo '<span class="egp-template-name">📄 ' . esc_html($template->post_title) . '</span>';
            echo '<span class="egp-template-countries">(' . esc_html(implode(', ', (array)$countries)) . ')</span>';
            echo '</div>';
        }
        
        // Render Elementor content
        echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($template_id);
        
        echo '</div>';
    }
    
    /**
     * Render fallback content
     */
    protected function render_fallback($template_id) {
        $fallback_mode = get_post_meta($template_id, 'egp_fallback_mode', true);
        
        if ($fallback_mode === 'show_default') {
            echo '<div class="egp-geo-section-fallback">';
            echo '<p>' . __('This content is not available in your region.', 'elementor-geo-popup') . '</p>';
            echo '</div>';
        }
        
        // Otherwise hide (render nothing)
    }
    
    /**
     * Get template options for select
     */
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
            if ($type === 'section' || empty($type)) {
                $options[$template->ID] = $template->post_title;
            }
        }
        
        return $options;
    }
    
    /**
     * Get countries list
     */
    protected function get_countries_list() {
        return [
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'JP' => 'Japan',
            'CN' => 'China',
            'IN' => 'India',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'NL' => 'Netherlands',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'PL' => 'Poland',
            'BE' => 'Belgium',
            'AT' => 'Austria',
            'CH' => 'Switzerland',
            'IE' => 'Ireland',
            'NZ' => 'New Zealand',
            'SG' => 'Singapore',
            'KR' => 'South Korea',
            'ZA' => 'South Africa',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'CO' => 'Colombia',
        ];
    }
}

