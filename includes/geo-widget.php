<?php
/**
 * Elementor Geo Widget
 *
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Geo Widget Class
 * 
 * A widget that can be dragged into Elementor designs to enable geo-targeting
 * for any content based on visitor country
 */
class EGP_Geo_Widget extends \Elementor\Widget_Base {

    /**
     * Get widget name
     */
    public function get_name() {
        return 'egp_geo_widget';
    }

    /**
     * Get widget title
     */
    public function get_title() {
        return esc_html__('Geo', 'elementor-geo-popup');
    }

    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-globe';
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
        return ['geo', 'location', 'country', 'targeting', 'geolocation'];
    }

    /**
     * Get script dependencies
     */
    public function get_script_depends() {
        return [];
    }

    /**
     * Get style dependencies
     */
    public function get_style_depends() {
        return ['egp-geo-widget'];
    }

    /**
     * Register widget controls
     */
    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__('Content', 'elementor-geo-popup'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'content_type',
            [
                'label' => esc_html__('Content Type', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'text',
                'options' => [
                    'text' => esc_html__('Text', 'elementor-geo-popup'),
                    'html' => esc_html__('HTML', 'elementor-geo-popup'),
                    'shortcode' => esc_html__('Shortcode', 'elementor-geo-popup'),
                    'template' => esc_html__('Template', 'elementor-geo-popup'),
                ],
            ]
        );

        $this->add_control(
            'content_text',
            [
                'label' => esc_html__('Text Content', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => esc_html__('This content is visible to visitors from specific countries.', 'elementor-geo-popup'),
                'condition' => [
                    'content_type' => 'text',
                ],
            ]
        );

        $this->add_control(
            'content_html',
            [
                'label' => esc_html__('HTML Content', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::CODE,
                'language' => 'html',
                'default' => '<p>This <strong>HTML content</strong> is visible to visitors from specific countries.</p>',
                'condition' => [
                    'content_type' => 'html',
                ],
            ]
        );

        $this->add_control(
            'content_shortcode',
            [
                'label' => esc_html__('Shortcode', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '[your_shortcode]',
                'condition' => [
                    'content_type' => 'shortcode',
                ],
            ]
        );

        $this->add_control(
            'content_template',
            [
                'label' => esc_html__('Template', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SELECT2,
                'options' => $this->get_templates_list(),
                'condition' => [
                    'content_type' => 'template',
                ],
            ]
        );

        $this->end_controls_section();

        // Geo Targeting Section
        $this->start_controls_section(
            'geo_targeting_section',
            [
                'label' => esc_html__('Geo Targeting', 'elementor-geo-popup'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'geo_targeting_enabled',
            [
                'label' => esc_html__('Enable Geo Targeting', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'elementor-geo-popup'),
                'label_off' => esc_html__('No', 'elementor-geo-popup'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'target_countries',
            [
                'label' => esc_html__('Target Countries', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'multiple' => true,
                'options' => $this->get_countries_list(),
                'default' => $this->get_preferred_countries(),
                'condition' => [
                    'geo_targeting_enabled' => 'yes',
                ],
                'description' => esc_html__('Select one or more countries to target. Hold Ctrl/Cmd to select multiple countries.', 'elementor-geo-popup'),
                'select2options' => [], // Disable Select2 to use native multi-select
            ]
        );

        $this->add_control(
            'fallback_behavior',
            [
                'label' => esc_html__('Fallback Behavior', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'hide',
                'options' => [
                    'hide' => esc_html__('Hide for non-matching countries', 'elementor-geo-popup'),
                    'show' => esc_html__('Show for all countries', 'elementor-geo-popup'),
                    'alternative' => esc_html__('Show alternative content', 'elementor-geo-popup'),
                ],
                'condition' => [
                    'geo_targeting_enabled' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'alternative_content',
            [
                'label' => esc_html__('Alternative Content', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => esc_html__('This content is visible to visitors from other countries.', 'elementor-geo-popup'),
                'condition' => [
                    'geo_targeting_enabled' => 'yes',
                    'fallback_behavior' => 'alternative',
                ],
            ]
        );

        $this->add_control(
            'debug_mode',
            [
                'label' => esc_html__('Debug Mode', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'elementor-geo-popup'),
                'label_off' => esc_html__('No', 'elementor-geo-popup'),
                'return_value' => 'yes',
                'default' => 'no',
                'description' => esc_html__('Show debug information in admin mode', 'elementor-geo-popup'),
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'style_section',
            [
                'label' => esc_html__('Style', 'elementor-geo-popup'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'content_typography',
                'selector' => '{{WRAPPER}} .egp-geo-content',
            ]
        );

        $this->add_control(
            'text_color',
            [
                'label' => esc_html__('Text Color', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .egp-geo-content' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'text_align',
            [
                'label' => esc_html__('Alignment', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => esc_html__('Left', 'elementor-geo-popup'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => esc_html__('Center', 'elementor-geo-popup'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => esc_html__('Right', 'elementor-geo-popup'),
                        'icon' => 'eicon-text-align-right',
                    ],
                    'justify' => [
                        'title' => esc_html__('Justified', 'elementor-geo-popup'),
                        'icon' => 'eicon-text-align-justify',
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .egp-geo-content' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output on the frontend
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Check if geo targeting is enabled
        if ($settings['geo_targeting_enabled'] !== 'yes') {
            $this->render_main_content($settings);
            return;
        }

        // Get visitor country
        $visitor_country = $this->get_visitor_country();
        
        // Check if visitor country matches target countries
        $is_match = in_array($visitor_country, $settings['target_countries']);
        
        // Debug mode for admin
        if ($settings['debug_mode'] === 'yes' && current_user_can('manage_options')) {
            echo '<div class="egp-debug-info" style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">';
            echo '<strong>Debug Info:</strong><br>';
            echo 'Visitor Country: ' . esc_html($visitor_country) . '<br>';
            echo 'Target Countries: ' . esc_html(implode(', ', $settings['target_countries'])) . '<br>';
            echo 'Match: ' . ($is_match ? 'Yes' : 'No') . '<br>';
            echo '</div>';
        }

        // Determine what to show
        if ($is_match) {
            // Show main content for matching countries
            $this->render_main_content($settings);
        } else {
            // Handle fallback behavior
            switch ($settings['fallback_behavior']) {
                case 'show':
                    $this->render_main_content($settings);
                    break;
                case 'alternative':
                    if (!empty($settings['alternative_content'])) {
                        echo '<div class="egp-geo-content egp-alternative-content">';
                        echo wp_kses_post($settings['alternative_content']);
                        echo '</div>';
                    }
                    break;
                case 'hide':
                default:
                    // Hide content - don't render anything
                    break;
            }
        }
    }

    /**
     * Render the main content
     */
    private function render_main_content($settings) {
        // Generate unique ID for this widget instance
        $widget_id = 'egp-widget-' . $this->get_id();
        $rule_id = $this->get_or_create_rule($settings);
        $element_type = $this->get_element_type();

        // Determine tracking strategy based on element type
        $tracking_attributes = $this->get_tracking_attributes($rule_id, $element_type);

        // Check if content contains forms for automatic form tracking
        $content_html = $this->get_content_html($settings);
        $has_form = $this->content_has_form($content_html);

        echo '<div class="egp-geo-content" data-rule-id="' . esc_attr($rule_id) . '" data-widget-id="' . esc_attr($widget_id) . '" data-element-type="' . esc_attr($element_type) . '"' . $tracking_attributes . '>';

        // Output content with automatic form tracking if forms are present
        if ($has_form) {
            echo $this->add_form_tracking($content_html, $rule_id);
        } else {
            echo $content_html;
        }

        echo '</div>';
    }

    /**
     * Get content HTML based on type
     */
    private function get_content_html($settings) {
        switch ($settings['content_type']) {
            case 'text':
                return wp_kses_post($settings['content_text']);

            case 'html':
                return wp_kses_post($settings['content_html']);

            case 'shortcode':
                if (!empty($settings['content_shortcode'])) {
                    return do_shortcode($settings['content_shortcode']);
                }
                return '';

            case 'template':
                if (!empty($settings['content_template'])) {
                    return \Elementor\Plugin::$instance->frontend->get_builder_content($settings['content_template'], true);
                }
                return '';

            default:
                return '';
        }
    }

    /**
     * Check if content contains forms
     */
    private function content_has_form($content) {
        return strpos($content, '<form') !== false || strpos($content, '[contact-form') !== false;
    }

    /**
     * Add automatic form tracking to content
     */
    private function add_form_tracking($content, $rule_id) {
        // Add form tracking attributes to any forms in the content
        $content = preg_replace(
            '/<form([^>]*)>/i',
            '<form$1 data-rule-id="' . esc_attr($rule_id) . '">',
            $content
        );

        return $content;
    }

    /**
     * Get the element type for tracking
     */
    private function get_element_type() {
        // Check if this is being rendered in a section/container context
        $element_data = $this->get_data();
        if (isset($element_data['elType'])) {
            return $element_data['elType'];
        }

        // Default to widget
        return 'widget';
    }

    /**
     * Get tracking attributes based on element type
     */
    private function get_tracking_attributes($rule_id, $element_type) {
        $attributes = '';

        // Add impression tracking for sections/containers
        if (in_array($element_type, ['section', 'container'])) {
            $attributes .= ' data-track-impression="true"';
        }

        // Add click tracking for interactive elements
        if ($element_type === 'widget') {
            $attributes .= ' onclick="egpTrackClick(' . esc_attr($rule_id) . ', \'' . esc_attr($element_type) . '\')"';
        }

        return $attributes;
    }

    /**
     * Get or create a geo rule for this widget
     * Note: This is now primarily handled by the automatic tracking system
     */
    private function get_or_create_rule($settings) {
        // Only create rules if geo targeting is enabled
        if ($settings['geo_targeting_enabled'] !== 'yes') {
            return 0;
        }

        // Check if a rule already exists for this widget (both old and new formats)
        $widget_id = $this->get_id();
        $existing_rules = get_posts(array(
            'post_type' => 'geo_rule',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'egp_widget_id',
                    'value' => $widget_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'egp_element_id',
                    'value' => $widget_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        ));

        if (!empty($existing_rules)) {
            return $existing_rules[0]->ID;
        }

        // Create a new rule for this widget
        $rule_data = array(
            'post_title' => 'Widget: ' . $this->get_title() . ' (ID: ' . $widget_id . ')',
            'post_type' => 'geo_rule',
            'post_status' => 'publish',
            'meta_input' => array(
                'egp_target_type' => 'widget',
                'egp_widget_id' => $widget_id,
                'egp_element_id' => $widget_id,
                'egp_element_type' => 'widget',
                'egp_countries' => $settings['target_countries'],
                'egp_active' => '1',
                'egp_clicks' => 0,
                'egp_views' => 0,
                'egp_impressions' => 0
            )
        );

        $rule_id = wp_insert_post($rule_data);

        if ($rule_id && !is_wp_error($rule_id)) {
            // Log rule creation for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("EGP Widget: Created rule ID {$rule_id} for widget {$widget_id}");
            }
            return $rule_id;
        }

        return 0;
    }

    /**
     * Get visitor country
     */
    private function get_visitor_country() {
        // Use the existing geo detection logic
        $geo_detect = EGP_Geo_Detect::get_instance();
        return $geo_detect->get_visitor_country();
    }

    /**
     * Get visitor IP
     */
    private function get_visitor_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return '';
    }

    /**
     * Check if IP is private
     */
    private function is_private_ip($ip) {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * Simple IP to country mapping (fallback)
     */
    private function simple_ip_to_country($ip) {
        // This is a very basic fallback - in production, you'd want to use MaxMind
        $ip_parts = explode('.', $ip);
        if (count($ip_parts) === 4) {
            $first_octet = (int)$ip_parts[0];
            if ($first_octet >= 1 && $first_octet <= 126) {
                return 'US'; // North America
            } elseif ($first_octet >= 128 && $first_octet <= 191) {
                return 'EU'; // Europe
            } elseif ($first_octet >= 192 && $first_octet <= 223) {
                return 'AS'; // Asia
            }
        }
        return 'US'; // Default
    }

    /**
     * Get countries list
     */
    private function get_countries_list() {
        return [
            'AF' => 'Afghanistan',
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'AS' => 'American Samoa',
            'AD' => 'Andorra',
            'AO' => 'Angola',
            'AI' => 'Anguilla',
            'AQ' => 'Antarctica',
            'AG' => 'Antigua and Barbuda',
            'AR' => 'Argentina',
            'AM' => 'Armenia',
            'AW' => 'Aruba',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'AZ' => 'Azerbaijan',
            'BS' => 'Bahamas',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BB' => 'Barbados',
            'BY' => 'Belarus',
            'BE' => 'Belgium',
            'BZ' => 'Belize',
            'BJ' => 'Benin',
            'BM' => 'Bermuda',
            'BT' => 'Bhutan',
            'BO' => 'Bolivia',
            'BA' => 'Bosnia and Herzegovina',
            'BW' => 'Botswana',
            'BV' => 'Bouvet Island',
            'BR' => 'Brazil',
            'IO' => 'British Indian Ocean Territory',
            'BN' => 'Brunei Darussalam',
            'BG' => 'Bulgaria',
            'BF' => 'Burkina Faso',
            'BI' => 'Burundi',
            'KH' => 'Cambodia',
            'CM' => 'Cameroon',
            'CA' => 'Canada',
            'CV' => 'Cape Verde',
            'KY' => 'Cayman Islands',
            'CF' => 'Central African Republic',
            'TD' => 'Chad',
            'CL' => 'Chile',
            'CN' => 'China',
            'CX' => 'Christmas Island',
            'CC' => 'Cocos (Keeling) Islands',
            'CO' => 'Colombia',
            'KM' => 'Comoros',
            'CG' => 'Congo',
            'CD' => 'Congo, the Democratic Republic of the',
            'CK' => 'Cook Islands',
            'CR' => 'Costa Rica',
            'CI' => 'Cote D\'Ivoire',
            'HR' => 'Croatia (Hrvatska)',
            'CU' => 'Cuba',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'DK' => 'Denmark',
            'DJ' => 'Djibouti',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'EC' => 'Ecuador',
            'EG' => 'Egypt',
            'SV' => 'El Salvador',
            'GQ' => 'Equatorial Guinea',
            'ER' => 'Eritrea',
            'EE' => 'Estonia',
            'ET' => 'Ethiopia',
            'FK' => 'Falkland Islands (Malvinas)',
            'FO' => 'Faroe Islands',
            'FJ' => 'Fiji',
            'FI' => 'Finland',
            'FR' => 'France',
            'GF' => 'French Guiana',
            'PF' => 'French Polynesia',
            'TF' => 'French Southern Territories',
            'GA' => 'Gabon',
            'GM' => 'Gambia',
            'GE' => 'Georgia',
            'DE' => 'Germany',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GR' => 'Greece',
            'GL' => 'Greenland',
            'GD' => 'Grenada',
            'GP' => 'Guadeloupe',
            'GU' => 'Guam',
            'GT' => 'Guatemala',
            'GN' => 'Guinea',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HT' => 'Haiti',
            'HM' => 'Heard Island and Mcdonald Islands',
            'VA' => 'Holy See (Vatican City State)',
            'HN' => 'Honduras',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'IS' => 'Iceland',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IR' => 'Iran, Islamic Republic of',
            'IQ' => 'Iraq',
            'IE' => 'Ireland',
            'IL' => 'Israel',
            'IT' => 'Italy',
            'JM' => 'Jamaica',
            'JP' => 'Japan',
            'JO' => 'Jordan',
            'KZ' => 'Kazakhstan',
            'KE' => 'Kenya',
            'KI' => 'Kiribati',
            'KP' => 'Korea, Democratic People\'s Republic of',
            'KR' => 'Korea, Republic of',
            'KW' => 'Kuwait',
            'KG' => 'Kyrgyzstan',
            'LA' => 'Lao People\'s Democratic Republic',
            'LV' => 'Latvia',
            'LB' => 'Lebanon',
            'LS' => 'Lesotho',
            'LR' => 'Liberia',
            'LY' => 'Libyan Arab Jamahiriya',
            'LI' => 'Liechtenstein',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MO' => 'Macao',
            'MK' => 'Macedonia, the Former Yugoslav Republic of',
            'MG' => 'Madagascar',
            'MW' => 'Malawi',
            'MY' => 'Malaysia',
            'MV' => 'Maldives',
            'ML' => 'Mali',
            'MT' => 'Malta',
            'MH' => 'Marshall Islands',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MU' => 'Mauritius',
            'YT' => 'Mayotte',
            'MX' => 'Mexico',
            'FM' => 'Micronesia, Federated States of',
            'MD' => 'Moldova, Republic of',
            'MC' => 'Monaco',
            'MN' => 'Mongolia',
            'MS' => 'Montserrat',
            'MA' => 'Morocco',
            'MZ' => 'Mozambique',
            'MM' => 'Myanmar',
            'NA' => 'Namibia',
            'NR' => 'Nauru',
            'NP' => 'Nepal',
            'NL' => 'Netherlands',
            'NC' => 'New Caledonia',
            'NZ' => 'New Zealand',
            'NI' => 'Nicaragua',
            'NE' => 'Niger',
            'NG' => 'Nigeria',
            'NU' => 'Niue',
            'NF' => 'Norfolk Island',
            'MP' => 'Northern Mariana Islands',
            'NO' => 'Norway',
            'OM' => 'Oman',
            'PK' => 'Pakistan',
            'PW' => 'Palau',
            'PS' => 'Palestinian Territory, Occupied',
            'PA' => 'Panama',
            'PG' => 'Papua New Guinea',
            'PY' => 'Paraguay',
            'PE' => 'Peru',
            'PH' => 'Philippines',
            'PN' => 'Pitcairn',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'PR' => 'Puerto Rico',
            'QA' => 'Qatar',
            'RE' => 'Reunion',
            'RO' => 'Romania',
            'RU' => 'Russian Federation',
            'RW' => 'Rwanda',
            'SH' => 'Saint Helena',
            'KN' => 'Saint Kitts and Nevis',
            'LC' => 'Saint Lucia',
            'PM' => 'Saint Pierre and Miquelon',
            'VC' => 'Saint Vincent and the Grenadines',
            'WS' => 'Samoa',
            'SM' => 'San Marino',
            'ST' => 'Sao Tome and Principe',
            'SA' => 'Saudi Arabia',
            'SN' => 'Senegal',
            'SC' => 'Seychelles',
            'SL' => 'Sierra Leone',
            'SG' => 'Singapore',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'SB' => 'Solomon Islands',
            'SO' => 'Somalia',
            'ZA' => 'South Africa',
            'GS' => 'South Georgia and the South Sandwich Islands',
            'ES' => 'Spain',
            'LK' => 'Sri Lanka',
            'SD' => 'Sudan',
            'SR' => 'Suriname',
            'SJ' => 'Svalbard and Jan Mayen',
            'SZ' => 'Swaziland',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'SY' => 'Syrian Arab Republic',
            'TW' => 'Taiwan, Province of China',
            'TJ' => 'Tajikistan',
            'TZ' => 'Tanzania, United Republic of',
            'TH' => 'Thailand',
            'TL' => 'Timor-Leste',
            'TG' => 'Togo',
            'TK' => 'Tokelau',
            'TO' => 'Tonga',
            'TT' => 'Trinidad and Tobago',
            'TN' => 'Tunisia',
            'TR' => 'Turkey',
            'TM' => 'Turkmenistan',
            'TC' => 'Turks and Caicos Islands',
            'TV' => 'Tuvalu',
            'UG' => 'Uganda',
            'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'UM' => 'United States Minor Outlying Islands',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VU' => 'Vanuatu',
            'VE' => 'Venezuela',
            'VN' => 'Vietnam',
            'VG' => 'Virgin Islands, British',
            'VI' => 'Virgin Islands, U.S.',
            'WF' => 'Wallis and Futuna',
            'EH' => 'Western Sahara',
            'YE' => 'Yemen',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
        ];
    }

    /**
     * Get preferred countries from admin settings
     */
    private function get_preferred_countries() {
        return get_option('egp_preferred_countries', ['US', 'CA', 'GB']);
    }

    /**
     * Get templates list
     */
    private function get_templates_list() {
        $templates = [];
        
        if (class_exists('\Elementor\Plugin')) {
            $posts = get_posts([
                'post_type' => 'elementor_library',
                'post_status' => 'publish',
                'numberposts' => -1,
            ]);
            
            foreach ($posts as $post) {
                $templates[$post->ID] = $post->post_title;
            }
        }
        
        return $templates;
    }

    /**
     * Render widget output in the editor
     */
    protected function content_template() {
        ?>
        <# if (settings.geo_targeting_enabled === 'yes') { #>
            <div class="egp-geo-content">
                <# if (settings.content_type === 'text') { #>
                    {{{ settings.content_text }}}
                <# } else if (settings.content_type === 'html') { #>
                    {{{ settings.content_html }}}
                <# } else if (settings.content_type === 'shortcode') { #>
                    [{{ settings.content_shortcode }}]
                <# } else if (settings.content_type === 'template') { #>
                    <div class="egp-template-placeholder">
                        <i class="eicon-document-file"></i>
                        <span>Template ID: {{ settings.content_template }}</span>
                    </div>
                <# } #>
            </div>
            
            <# if (settings.debug_mode === 'yes') { #>
                <div class="egp-debug-info" style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">
                    <strong>Debug Info (Editor Mode):</strong><br>
                    Target Countries: {{ settings.target_countries.join(', ') }}<br>
                    Fallback: {{ settings.fallback_behavior }}
                </div>
            <# } #>
        <# } else { #>
            <div class="egp-geo-content">
                <# if (settings.content_type === 'text') { #>
                    {{{ settings.content_text }}}
                <# } else if (settings.content_type === 'html') { #>
                    {{{ settings.content_html }}}
                <# } else if (settings.content_type === 'shortcode') { #>
                    [{{ settings.content_shortcode }}]
                <# } else if (settings.content_type === 'template') { #>
                    <div class="egp-template-placeholder">
                        <i class="eicon-document-file"></i>
                        <span>Template ID: {{ settings.content_template }}</span>
                    </div>
                <# } #>
            </div>
        <# } #>
        <?php
    }
}
