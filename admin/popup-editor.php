<?php
/**
 * Elementor Popup Editor Integration
 * 
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Popup Editor Integration Class
 */
class EGP_Popup_Editor {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('elementor/init', array($this, 'init_popup_editor'));
        // Admin list column for Elementor Library (Popup) – show geo targeting summary
        add_filter('manage_edit-elementor_library_columns', array($this, 'add_geo_column'));
        add_action('manage_elementor_library_posts_custom_column', array($this, 'render_geo_column'), 10, 2);
    }
    
    /**
     * Initialize popup editor integration
     */
    public function init_popup_editor() {
        // Add our section under the Advanced tab for Popup document
        add_action('elementor/element/popup/section_advanced/after_section_end', array($this, 'add_geo_targeting_section'));
        // Fallback older section id
        add_action('elementor/element/popup/section_popup_advanced/after_section_end', array($this, 'add_geo_targeting_section'));

        // Add custom CSS and JS for the popup editor
        add_action('elementor/editor/before_enqueue_scripts', array($this, 'enqueue_editor_scripts'));
    }
    
    /**
     * Add geo targeting controls to popup layout section
     */
    public function add_geo_targeting_section($element) {
        $element->start_controls_section(
            'egp_geo_targeting_section',
            array(
                'label' => __('Geo Targeting', 'elementor-geo-popup'),
                'tab' => \Elementor\Controls_Manager::TAB_ADVANCED,
            )
        );
        
        $element->add_control(
            'egp_enable_geo_targeting',
            array(
                'label' => __('Enable Geo Targeting', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'elementor-geo-popup'),
                'label_off' => __('No', 'elementor-geo-popup'),
                'return_value' => 'yes',
                'default' => 'no',
            )
        );

        $element->add_control(
            'egp_geo_targeting_description',
            array(
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => '<div class="elementor-control-raw-html">
                    <p>' . __('Configure which countries this popup should be shown to.', 'elementor-geo-popup') . '</p>
                </div>',
                'condition' => array(
                    'egp_enable_geo_targeting' => 'yes',
                ),
            )
        );
        
        $element->add_control(
            'egp_countries',
            array(
                'label' => __('Target Countries', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'multiple' => true,
                'label_block' => true,
                'options' => $this->get_countries_list(),
                'default' => array(),
                'render_type' => 'none',
                'condition' => array(
                    'egp_enable_geo_targeting' => 'yes',
                ),
            )
        );
        
        $element->add_control(
            'egp_fallback_behavior',
            array(
                'label' => __('Fallback Behavior', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'inherit',
                'options' => array(
                    'inherit' => __('Inherit from global settings', 'elementor-geo-popup'),
                    'show_to_all' => __('Show to all visitors', 'elementor-geo-popup'),
                    'show_to_none' => __('Show to none', 'elementor-geo-popup'),
                    'show_default' => __('Show default popup', 'elementor-geo-popup'),
                ),
                'condition' => array(
                    'egp_enable_geo_targeting' => 'yes',
                ),
            )
        );
        
        $element->add_control(
            'egp_geo_targeting_note',
            array(
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => '<div class="elementor-control-raw-html">
                    <p><em>' . __('Note: These settings will control whether this popup should be displayed based on the visitor country.', 'elementor-geo-popup') . '</em></p>
                </div>',
                'condition' => array(
                    'egp_enable_geo_targeting' => 'yes',
                ),
            )
        );
        
        $element->end_controls_section();
    }
    
    /**
     * Get countries list for select control
     */
    private function get_countries_list() {
        // Try full list from assets/data/countries.json
        $json_path = EGP_PLUGIN_DIR . 'assets/data/countries.json';
        if (file_exists($json_path)) {
            $raw = file_get_contents($json_path);
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && !empty($decoded)) {
                $all = array();
                foreach ($decoded as $item) {
                    if (isset($item['code']) && isset($item['name'])) {
                        $all[$item['code']] = $item['name'];
                    }
                }
                if (!empty($all)) {
                    asort($all, SORT_NATURAL | SORT_FLAG_CASE);
                    foreach ($all as $code => $name) { $all[$code] = sprintf('%s (%s)', $name, $code); }
                    return $all;
                }
            }
        }

        $countries = array(
            'US' => __('United States', 'elementor-geo-popup'),
            'CA' => __('Canada', 'elementor-geo-popup'),
            'GB' => __('United Kingdom', 'elementor-geo-popup'),
            'DE' => __('Germany', 'elementor-geo-popup'),
            'FR' => __('France', 'elementor-geo-popup'),
            'IT' => __('Italy', 'elementor-geo-popup'),
            'ES' => __('Spain', 'elementor-geo-popup'),
            'NL' => __('Netherlands', 'elementor-geo-popup'),
            'BE' => __('Belgium', 'elementor-geo-popup'),
            'CH' => __('Switzerland', 'elementor-geo-popup'),
            'AT' => __('Austria', 'elementor-geo-popup'),
            'SE' => __('Sweden', 'elementor-geo-popup'),
            'NO' => __('Norway', 'elementor-geo-popup'),
            'DK' => __('Denmark', 'elementor-geo-popup'),
            'FI' => __('Finland', 'elementor-geo-popup'),
            'PL' => __('Poland', 'elementor-geo-popup'),
            'CZ' => __('Czech Republic', 'elementor-geo-popup'),
            'HU' => __('Hungary', 'elementor-geo-popup'),
            'RO' => __('Romania', 'elementor-geo-popup'),
            'BG' => __('Bulgaria', 'elementor-geo-popup'),
            'HR' => __('Croatia', 'elementor-geo-popup'),
            'SI' => __('Slovenia', 'elementor-geo-popup'),
            'SK' => __('Slovakia', 'elementor-geo-popup'),
            'LT' => __('Lithuania', 'elementor-geo-popup'),
            'LV' => __('Latvia', 'elementor-geo-popup'),
            'EE' => __('Estonia', 'elementor-geo-popup'),
            'IE' => __('Ireland', 'elementor-geo-popup'),
            'PT' => __('Portugal', 'elementor-geo-popup'),
            'GR' => __('Greece', 'elementor-geo-popup'),
            'CY' => __('Cyprus', 'elementor-geo-popup'),
            'MT' => __('Malta', 'elementor-geo-popup'),
            'LU' => __('Luxembourg', 'elementor-geo-popup'),
            'AU' => __('Australia', 'elementor-geo-popup'),
            'NZ' => __('New Zealand', 'elementor-geo-popup'),
            'JP' => __('Japan', 'elementor-geo-popup'),
            'KR' => __('South Korea', 'elementor-geo-popup'),
            'CN' => __('China', 'elementor-geo-popup'),
            'IN' => __('India', 'elementor-geo-popup'),
            'BR' => __('Brazil', 'elementor-geo-popup'),
            'MX' => __('Mexico', 'elementor-geo-popup'),
            'AR' => __('Argentina', 'elementor-geo-popup'),
            'CL' => __('Chile', 'elementor-geo-popup'),
            'CO' => __('Colombia', 'elementor-geo-popup'),
            'PE' => __('Peru', 'elementor-geo-popup'),
            'VE' => __('Venezuela', 'elementor-geo-popup'),
            'ZA' => __('South Africa', 'elementor-geo-popup'),
            'EG' => __('Egypt', 'elementor-geo-popup'),
            'NG' => __('Nigeria', 'elementor-geo-popup'),
            'KE' => __('Kenya', 'elementor-geo-popup'),
            'MA' => __('Morocco', 'elementor-geo-popup'),
            'TN' => __('Tunisia', 'elementor-geo-popup'),
            'DZ' => __('Algeria', 'elementor-geo-popup'),
            'LY' => __('Libya', 'elementor-geo-popup'),
            'SD' => __('Sudan', 'elementor-geo-popup'),
            'ET' => __('Ethiopia', 'elementor-geo-popup'),
            'GH' => __('Ghana', 'elementor-geo-popup'),
            'CI' => __('Ivory Coast', 'elementor-geo-popup'),
            'SN' => __('Senegal', 'elementor-geo-popup'),
            'ML' => __('Mali', 'elementor-geo-popup'),
            'BF' => __('Burkina Faso', 'elementor-geo-popup'),
            'NE' => __('Niger', 'elementor-geo-popup'),
            'TD' => __('Chad', 'elementor-geo-popup'),
            'CF' => __('Central African Republic', 'elementor-geo-popup'),
            'CM' => __('Cameroon', 'elementor-geo-popup'),
            'GQ' => __('Equatorial Guinea', 'elementor-geo-popup'),
            'GA' => __('Gabon', 'elementor-geo-popup'),
            'CG' => __('Republic of the Congo', 'elementor-geo-popup'),
            'CD' => __('Democratic Republic of the Congo', 'elementor-geo-popup'),
            'AO' => __('Angola', 'elementor-geo-popup'),
            'ZM' => __('Zambia', 'elementor-geo-popup'),
            'ZW' => __('Zimbabwe', 'elementor-geo-popup'),
            'BW' => __('Botswana', 'elementor-geo-popup'),
            'NA' => __('Namibia', 'elementor-geo-popup'),
            'SZ' => __('Eswatini', 'elementor-geo-popup'),
            'LS' => __('Lesotho', 'elementor-geo-popup'),
            'MG' => __('Madagascar', 'elementor-geo-popup'),
            'MU' => __('Mauritius', 'elementor-geo-popup'),
            'SC' => __('Seychelles', 'elementor-geo-popup'),
            'KM' => __('Comoros', 'elementor-geo-popup'),
            'DJ' => __('Djibouti', 'elementor-geo-popup'),
            'SO' => __('Somalia', 'elementor-geo-popup'),
            'ER' => __('Eritrea', 'elementor-geo-popup'),
            'SS' => __('South Sudan', 'elementor-geo-popup'),
            'RW' => __('Rwanda', 'elementor-geo-popup'),
            'BI' => __('Burundi', 'elementor-geo-popup'),
            'TZ' => __('Tanzania', 'elementor-geo-popup'),
            'UG' => __('Uganda', 'elementor-geo-popup'),
            'MZ' => __('Mozambique', 'elementor-geo-popup'),
            'MW' => __('Malawi', 'elementor-geo-popup'),
            'ZM' => __('Zambia', 'elementor-geo-popup'),
            'ZW' => __('Zimbabwe', 'elementor-geo-popup'),
            'BW' => __('Botswana', 'elementor-geo-popup'),
            'NA' => __('Namibia', 'elementor-geo-popup'),
            'SZ' => __('Eswatini', 'elementor-geo-popup'),
            'LS' => __('Lesotho', 'elementor-geo-popup'),
            'MG' => __('Madagascar', 'elementor-geo-popup'),
            'MU' => __('Mauritius', 'elementor-geo-popup'),
            'SC' => __('Seychelles', 'elementor-geo-popup'),
            'KM' => __('Comoros', 'elementor-geo-popup'),
            'DJ' => __('Djibouti', 'elementor-geo-popup'),
            'SO' => __('Somalia', 'elementor-geo-popup'),
            'ER' => __('Eritrea', 'elementor-geo-popup'),
            'SS' => __('South Sudan', 'elementor-geo-popup'),
            'RW' => __('Rwanda', 'elementor-geo-popup'),
            'BI' => __('Burundi', 'elementor-geo-popup'),
            'TZ' => __('Tanzania', 'elementor-geo-popup'),
            'UG' => __('Uganda', 'elementor-geo-popup'),
            'MZ' => __('Mozambique', 'elementor-geo-popup'),
            'MW' => __('Malawi', 'elementor-geo-popup'),
            'SG' => __('Singapore', 'elementor-geo-popup'),
        );

        // Enhance labels for UX to include ISO code, e.g., "United Kingdom (GB)"
        foreach ($countries as $code => $name) {
            $countries[$code] = sprintf('%s (%s)', $name, $code);
        }
        
        return $countries;
    }
    
    /**
     * Enqueue editor scripts
     */
    public function enqueue_editor_scripts() {
        $editor_js_path = EGP_PLUGIN_DIR . 'assets/js/popup-editor.js';
        $editor_js_ver  = @filemtime($editor_js_path) ?: EGP_VERSION;
        wp_enqueue_script(
            'egp-popup-editor',
            EGP_PLUGIN_URL . 'assets/js/popup-editor.js',
            array('jquery', 'elementor-editor'),
            $editor_js_ver,
            true
        );
        
        wp_localize_script('egp-popup-editor', 'egpPopupEditor', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('egp_admin_nonce'),
            'assetsUrl' => EGP_PLUGIN_URL . 'assets/',
            'isEditor' => true,
            'preferredCountries' => get_option('egp_preferred_countries', array('US','CA','GB')),
            'strings' => array(
                'saving' => __('Saving geo targeting settings...', 'elementor-geo-popup'),
                'saved' => __('Geo targeting settings saved!', 'elementor-geo-popup'),
                'error' => __('Error saving settings.', 'elementor-geo-popup'),
                'usePreferred' => __('Use Preferred Countries', 'elementor-geo-popup'),
            )
        ));
    }

    /**
     * Add a GEO column to Elementor Library list
     */
    public function add_geo_column($columns) {
        // Insert after the 'type' column if present
        $new = array();
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'type') {
                $new['egp_geo'] = __('Geo Targeting', 'elementor-geo-popup');
            }
        }
        if (!isset($new['egp_geo'])) {
            $new['egp_geo'] = __('Geo Targeting', 'elementor-geo-popup');
        }
        return $new;
    }

    /**
     * Render GEO column content
     */
    public function render_geo_column($column, $post_id) {
        if ($column !== 'egp_geo') {
            return;
        }
        // Only relevant for popups
        $type = get_post_meta($post_id, '_elementor_template_type', true);
        if ($type !== 'popup') {
            echo '—';
            return;
        }
        $settings = get_post_meta($post_id, '_elementor_page_settings', true);
        $enabled = is_array($settings) && isset($settings['egp_enable_geo_targeting']) && $settings['egp_enable_geo_targeting'] === 'yes';
        if (!$enabled) {
            echo '<span title="' . esc_attr__('No explicit targeting set on this popup', 'elementor-geo-popup') . '">ALL</span>';
            return;
        }
        $countries = array();
        if (!empty($settings['egp_countries']) && is_array($settings['egp_countries'])) {
            foreach ($settings['egp_countries'] as $c) {
                $code = strtoupper(sanitize_text_field($c));
                if (class_exists('EGP_Geo_Detect')) {
                    $label = EGP_Geo_Detect::get_country_name($code);
                    $countries[] = esc_html($label) . ' (' . esc_html($code) . ')';
                } else {
                    $countries[] = esc_html($code);
                }
            }
        }
        if (empty($countries)) {
            echo 'ALL';
        } else {
            echo wp_kses_post(implode(', ', $countries));
        }
    }
    
    // No manual AJAX saving needed; Elementor stores these settings with the popup document
}

