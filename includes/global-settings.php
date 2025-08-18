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
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => $this->get_countries_list(),
                'default' => $this->get_preferred_countries(),
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
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => $this->get_countries_list(),
                'default' => $this->get_preferred_countries(),
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
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => $this->get_countries_list(),
                'default' => $this->get_preferred_countries(),
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
        wp_enqueue_script(
            'egp-global-settings',
            EGP_PLUGIN_URL . 'assets/js/global-settings.js',
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
}
