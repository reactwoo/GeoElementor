<?php
/**
 * Widget Registration
 *
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget Registration Class
 */
class EGP_Widget_Registration {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        add_action('elementor/elements/categories_registered', [$this, 'add_widget_categories']);
        add_action('elementor/frontend/after_enqueue_styles', [$this, 'enqueue_widget_styles']);
    }

    /**
     * Register widgets
     */
    public function register_widgets($widgets_manager) {
        // Include the Geo Widget class
        require_once EGP_PLUGIN_DIR . 'includes/geo-widget.php';
        
        // Register the Geo Widget
        $widgets_manager->register(new EGP_Geo_Widget());
    }

    /**
     * Add custom widget categories
     */
    public function add_widget_categories($elements_manager) {
        $elements_manager->add_category(
            'elementor-geo-popup',
            [
                'title' => esc_html__('Geo Popup', 'elementor-geo-popup'),
                'icon' => 'eicon-globe',
            ]
        );
    }

    /**
     * Enqueue widget styles
     */
    public function enqueue_widget_styles() {
        wp_enqueue_style(
            'egp-geo-widget',
            EGP_PLUGIN_URL . 'assets/css/geo-widget.css',
            [],
            EGP_VERSION
        );
    }
}
