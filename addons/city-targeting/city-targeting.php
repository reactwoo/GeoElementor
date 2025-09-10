<?php
/**
 * City Targeting Add-On
 * 
 * Provides city-based targeting for Geo Elementor
 * 
 * @package ElementorGeoPopup
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include base add-on class
require_once EGP_PLUGIN_DIR . 'includes/addon-base.php';

/**
 * City Targeting Add-On Class
 */
class EGP_City_Targeting_Addon extends EGP_Base_Addon {
    
    /**
     * City data cache
     */
    private $city_data = array();
    
    /**
     * API endpoints
     */
    private $api_endpoints = array(
        'geocoding' => 'https://api.openweathermap.org/geo/1.0/direct',
        'reverse_geocoding' => 'https://api.openweathermap.org/geo/1.0/reverse',
        'weather' => 'https://api.openweathermap.org/data/2.5/weather'
    );
    
    /**
     * Get add-on ID
     */
    protected function get_addon_id() {
        return 'city-targeting';
    }
    
    /**
     * Get add-on data
     */
    protected function get_addon_data() {
        return array(
            'id' => 'city-targeting',
            'name' => 'City Targeting',
            'description' => 'Target content based on visitor city location',
            'version' => '1.0.0',
            'author' => 'ReactWoo',
            'author_uri' => 'https://reactwoo.com',
            'plugin_uri' => 'https://reactwoo.com',
            'requires' => '1.0.0',
            'tested' => '1.0.1',
            'file' => 'city-targeting/city-targeting.php',
            'class' => 'EGP_City_Targeting_Addon',
            'category' => 'geo-targeting',
            'tags' => array('city', 'location', 'geo'),
            'screenshot' => '',
            'icon' => 'eicon-map-pin',
            'premium' => false,
            'status' => 'available'
        );
    }
    
    /**
     * Initialize hooks
     */
    protected function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_egp_city_get_cities', array($this, 'ajax_get_cities'));
        add_action('wp_ajax_egp_city_get_visitor_city', array($this, 'ajax_get_visitor_city'));
        add_action('wp_ajax_nopriv_egp_city_get_visitor_city', array($this, 'ajax_get_visitor_city'));
        add_action('wp_ajax_egp_city_save_settings', array($this, 'ajax_save_settings'));
        
        // Add city detection to geo detection
        add_filter('egp_visitor_data', array($this, 'add_city_data'));
        
        // Add city targeting to popup guard
        add_filter('egp_popup_guard_data', array($this, 'add_city_guard_data'));
    }
    
    /**
     * Initialize Elementor integration
     */
    protected function init_elementor_integration() {
        add_action('elementor/widgets/widgets_registered', array($this, 'add_city_controls_to_widgets'));
        add_action('elementor/elements/elements_registered', array($this, 'add_city_controls_to_containers'));
    }
    
    /**
     * Initialize frontend
     */
    protected function init_frontend() {
        add_action('wp_head', array($this, 'inject_city_detection_script'));
        add_action('wp_footer', array($this, 'inject_city_targeting_script'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'elementor-geo-popup',
            __('City Targeting Settings', 'elementor-geo-popup'),
            __('City Settings', 'elementor-geo-popup'),
            'manage_options',
            'egp-city-settings',
            array($this, 'render_admin_settings')
        );
    }
    
    /**
     * Render admin settings
     */
    public function render_admin_settings() {
        $api_key = $this->get_setting('openweather_api_key', '');
        $fallback_to_country = $this->get_setting('fallback_to_country', true);
        $cache_duration = $this->get_setting('cache_duration', 3600);
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('City Targeting Settings', 'elementor-geo-popup'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('egp_city_settings', 'egp_city_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="openweather_api_key"><?php echo esc_html__('OpenWeatherMap API Key', 'elementor-geo-popup'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="openweather_api_key" name="openweather_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                            <p class="description">
                                <?php echo esc_html__('Get your free API key from', 'elementor-geo-popup'); ?> 
                                <a href="https://openweathermap.org/api" target="_blank">OpenWeatherMap</a>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="fallback_to_country"><?php echo esc_html__('Fallback to Country', 'elementor-geo-popup'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="fallback_to_country" name="fallback_to_country" value="1" <?php checked($fallback_to_country); ?> />
                            <p class="description">
                                <?php echo esc_html__('Use country-based targeting when city detection fails', 'elementor-geo-popup'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cache_duration"><?php echo esc_html__('Cache Duration (seconds)', 'elementor-geo-popup'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="cache_duration" name="cache_duration" value="<?php echo esc_attr($cache_duration); ?>" min="300" max="86400" />
                            <p class="description">
                                <?php echo esc_html__('How long to cache city detection results (300-86400 seconds)', 'elementor-geo-popup'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Settings', 'elementor-geo-popup')); ?>
            </form>
            
            <?php if (!empty($api_key)): ?>
                <div class="egp-city-test-section" style="margin-top: 30px;">
                    <h2><?php echo esc_html__('Test City Detection', 'elementor-geo-popup'); ?></h2>
                    <p><?php echo esc_html__('Test city detection for your current location:', 'elementor-geo-popup'); ?></p>
                    <button type="button" id="egp-test-city-detection" class="button button-secondary">
                        <?php echo esc_html__('Test Detection', 'elementor-geo-popup'); ?>
                    </button>
                    <div id="egp-city-test-result" style="margin-top: 10px;"></div>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#egp-test-city-detection').on('click', function() {
                var $button = $(this);
                var $result = $('#egp-city-test-result');
                
                $button.prop('disabled', true).text('<?php echo esc_js(__('Testing...', 'elementor-geo-popup')); ?>');
                $result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'egp_city_get_visitor_city',
                        nonce: '<?php echo wp_create_nonce('egp_city_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            $result.html(
                                '<div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; border-radius: 4px;">' +
                                '<strong><?php echo esc_js(__('Detection Result:', 'elementor-geo-popup')); ?></strong><br>' +
                                '<?php echo esc_js(__('City:', 'elementor-geo-popup')); ?> ' + (data.city || '<?php echo esc_js(__('Unknown', 'elementor-geo-popup')); ?>') + '<br>' +
                                '<?php echo esc_js(__('Country:', 'elementor-geo-popup')); ?> ' + (data.country || '<?php echo esc_js(__('Unknown', 'elementor-geo-popup')); ?>') + '<br>' +
                                '<?php echo esc_js(__('IP:', 'elementor-geo-popup')); ?> ' + (data.ip || '<?php echo esc_js(__('Unknown', 'elementor-geo-popup')); ?>') +
                                '</div>'
                            );
                        } else {
                            $result.html(
                                '<div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px;">' +
                                '<strong><?php echo esc_js(__('Error:', 'elementor-geo-popup')); ?></strong> ' + (response.data || '<?php echo esc_js(__('Unknown error', 'elementor-geo-popup')); ?>') +
                                '</div>'
                            );
                        }
                    },
                    error: function() {
                        $result.html(
                            '<div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px;">' +
                            '<strong><?php echo esc_js(__('Error:', 'elementor-geo-popup')); ?></strong> <?php echo esc_js(__('AJAX request failed', 'elementor-geo-popup')); ?>' +
                            '</div>'
                        );
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php echo esc_js(__('Test Detection', 'elementor-geo-popup')); ?>');
                    }
                });
            });
        });
        </script>
        <?php
        
        // Handle form submission
        if (isset($_POST['egp_city_nonce']) && wp_verify_nonce($_POST['egp_city_nonce'], 'egp_city_settings')) {
            $this->save_settings_from_form();
        }
    }
    
    /**
     * Save settings from form
     */
    private function save_settings_from_form() {
        $settings = array(
            'openweather_api_key' => sanitize_text_field($_POST['openweather_api_key']),
            'fallback_to_country' => isset($_POST['fallback_to_country']),
            'cache_duration' => intval($_POST['cache_duration'])
        );
        
        $this->save_settings($settings);
        
        echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'elementor-geo-popup') . '</p></div>';
    }
    
    /**
     * Add city controls to widgets
     */
    public function add_city_controls_to_widgets($widgets_manager) {
        $widget_types = $widgets_manager->get_widget_types();
        
        foreach ($widget_types as $widget_type => $widget) {
            $this->add_city_controls_to_element($widget);
        }
    }
    
    /**
     * Add city controls to containers
     */
    public function add_city_controls_to_containers($elements_manager) {
        if (method_exists($elements_manager, 'get_element_types')) {
            $element_types = $elements_manager->get_element_types();
            if (isset($element_types['container'])) {
                $this->add_city_controls_to_element($element_types['container']);
            }
        }
    }
    
    /**
     * Add city controls to element
     */
    private function add_city_controls_to_element($element) {
        // Check if element already has our controls
        $controls = $element->get_controls();
        if (isset($controls['egp_city_targeting'])) {
            return;
        }
        
        // Inject our section
        if (method_exists($element, 'start_injection')) {
            $element->start_injection(array(
                'type' => 'section',
                'at' => 'start',
                'of' => 'section_advanced',
            ));
        }
        
        // Add controls section
        $element->start_controls_section(
            'egp_city_targeting',
            array(
                'label' => __('City Targeting', 'elementor-geo-popup'),
                'tab' => \Elementor\Controls_Manager::TAB_ADVANCED,
            ),
            array(
                'priority' => 2, // After geo targeting
            )
        );
        
        // Check if city targeting is enabled
        $api_key = $this->get_setting('openweather_api_key');
        if (empty($api_key)) {
            $element->add_control(
                'egp_city_setup_notice',
                array(
                    'type' => \Elementor\Controls_Manager::RAW_HTML,
                    'raw' => '<div style="background:#fff8e5;border:1px solid #ffe08a;padding:10px;border-radius:4px;">'
                           . esc_html__('City targeting requires an OpenWeatherMap API key.', 'elementor-geo-popup')
                           . ' <a href="' . esc_url(admin_url('admin.php?page=egp-city-settings')) . '" target="_blank">' . esc_html__('Configure Settings', 'elementor-geo-popup') . '</a>'
                           . '</div>',
                )
            );
        } else {
            $element->add_control(
                'egp_city_enabled',
                array(
                    'label' => __('Enable City Targeting', 'elementor-geo-popup'),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => __('On', 'elementor-geo-popup'),
                    'label_off' => __('Off', 'elementor-geo-popup'),
                    'return_value' => 'yes',
                    'default' => '',
                )
            );
            
            $element->add_control(
                'egp_city_targets',
                array(
                    'label' => __('Target Cities', 'elementor-geo-popup'),
                    'type' => \Elementor\Controls_Manager::TEXTAREA,
                    'description' => __('Enter city names, one per line. Use format: "City, Country" for better accuracy.', 'elementor-geo-popup'),
                    'condition' => array('egp_city_enabled' => 'yes'),
                    'placeholder' => "New York, US\nLondon, GB\nParis, FR",
                )
            );
            
            $element->add_control(
                'egp_city_fallback',
                array(
                    'label' => __('Fallback Behavior', 'elementor-geo-popup'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'default' => 'hide',
                    'options' => array(
                        'hide' => __('Hide for non-matching cities', 'elementor-geo-popup'),
                        'show' => __('Show for all cities', 'elementor-geo-popup'),
                        'country' => __('Use country targeting as fallback', 'elementor-geo-popup'),
                    ),
                    'condition' => array('egp_city_enabled' => 'yes'),
                )
            );
        }
        
        $element->end_controls_section();
        
        if (method_exists($element, 'end_injection')) {
            $element->end_injection();
        }
    }
    
    /**
     * Inject city detection script
     */
    public function inject_city_detection_script() {
        // Only inject on frontend
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        
        $api_key = $this->get_setting('openweather_api_key');
        if (empty($api_key)) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        (function() {
            'use strict';
            
            var egpCityData = {
                apiKey: <?php echo json_encode($api_key); ?>,
                ajaxUrl: <?php echo json_encode(admin_url('admin-ajax.php')); ?>,
                nonce: <?php echo json_encode(wp_create_nonce('egp_city_nonce')); ?>,
                detected: false,
                city: null,
                country: null,
                coordinates: null
            };
            
            function detectCity() {
                if (egpCityData.detected) {
                    return;
                }
                
                // Try to get city from cached data first
                var cached = sessionStorage.getItem('egp_city_data');
                if (cached) {
                    try {
                        var data = JSON.parse(cached);
                        if (data.city && data.country) {
                            egpCityData.city = data.city;
                            egpCityData.country = data.country;
                            egpCityData.coordinates = data.coordinates;
                            egpCityData.detected = true;
                            return;
                        }
                    } catch (e) {
                        // Invalid cached data, continue with detection
                    }
                }
                
                // Use AJAX to get city data
                var xhr = new XMLHttpRequest();
                xhr.open('POST', egpCityData.ajaxUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success && response.data) {
                                egpCityData.city = response.data.city;
                                egpCityData.country = response.data.country;
                                egpCityData.coordinates = response.data.coordinates;
                                egpCityData.detected = true;
                                
                                // Cache the result
                                sessionStorage.setItem('egp_city_data', JSON.stringify({
                                    city: egpCityData.city,
                                    country: egpCityData.country,
                                    coordinates: egpCityData.coordinates,
                                    timestamp: Date.now()
                                }));
                            }
                        } catch (e) {
                            console.error('EGP City: Failed to parse response', e);
                        }
                    }
                };
                xhr.send('action=egp_city_get_visitor_city&nonce=' + encodeURIComponent(egpCityData.nonce));
            }
            
            // Start detection when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', detectCity);
            } else {
                detectCity();
            }
            
            // Make data available globally
            window.egpCityData = egpCityData;
        })();
        </script>
        <?php
    }
    
    /**
     * Inject city targeting script
     */
    public function inject_city_targeting_script() {
        // Only inject on frontend
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        (function() {
            'use strict';
            
            function applyCityTargeting() {
                if (!window.egpCityData || !window.egpCityData.detected) {
                    return;
                }
                
                var city = window.egpCityData.city;
                var country = window.egpCityData.country;
                
                if (!city) {
                    return;
                }
                
                // Find elements with city targeting
                var elements = document.querySelectorAll('[data-egp-city-targeting]');
                
                elements.forEach(function(element) {
                    var targets = element.getAttribute('data-egp-city-targeting');
                    var fallback = element.getAttribute('data-egp-city-fallback') || 'hide';
                    
                    if (!targets) {
                        return;
                    }
                    
                    var targetCities = targets.split('\n').map(function(city) {
                        return city.trim().toLowerCase();
                    }).filter(function(city) {
                        return city.length > 0;
                    });
                    
                    var isMatch = targetCities.some(function(targetCity) {
                        return city.toLowerCase().includes(targetCity) || 
                               targetCity.includes(city.toLowerCase());
                    });
                    
                    if (isMatch) {
                        element.style.display = '';
                        element.style.visibility = 'visible';
                    } else {
                        if (fallback === 'show') {
                            element.style.display = '';
                            element.style.visibility = 'visible';
                        } else if (fallback === 'country') {
                            // Check country targeting
                            var countryTargets = element.getAttribute('data-egp-country-targeting');
                            if (countryTargets && countryTargets.includes(country)) {
                                element.style.display = '';
                                element.style.visibility = 'visible';
                            } else {
                                element.style.display = 'none';
                                element.style.visibility = 'hidden';
                            }
                        } else {
                            element.style.display = 'none';
                            element.style.visibility = 'hidden';
                        }
                    }
                });
            }
            
            // Apply targeting when city data is available
            function checkAndApply() {
                if (window.egpCityData && window.egpCityData.detected) {
                    applyCityTargeting();
                } else {
                    setTimeout(checkAndApply, 100);
                }
            }
            
            // Start checking
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', checkAndApply);
            } else {
                checkAndApply();
            }
        })();
        </script>
        <?php
    }
    
    /**
     * AJAX: Get cities
     */
    public function ajax_get_cities() {
        check_ajax_referer('egp_city_nonce', 'nonce');
        
        $query = sanitize_text_field($_POST['query']);
        $cities = $this->search_cities($query);
        
        wp_send_json_success($cities);
    }
    
    /**
     * AJAX: Get visitor city
     */
    public function ajax_get_visitor_city() {
        check_ajax_referer('egp_city_nonce', 'nonce');
        
        $visitor_data = $this->get_visitor_data();
        $city_data = $this->detect_city($visitor_data['ip']);
        
        if ($city_data) {
            wp_send_json_success($city_data);
        } else {
            wp_send_json_error(__('Could not detect city', 'elementor-geo-popup'));
        }
    }
    
    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('egp_city_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $settings = array(
            'openweather_api_key' => sanitize_text_field($_POST['openweather_api_key']),
            'fallback_to_country' => isset($_POST['fallback_to_country']),
            'cache_duration' => intval($_POST['cache_duration'])
        );
        
        $this->save_settings($settings);
        
        wp_send_json_success(__('Settings saved successfully', 'elementor-geo-popup'));
    }
    
    /**
     * Detect city from IP
     */
    private function detect_city($ip) {
        if (empty($ip) || $this->is_private_ip($ip)) {
            return false;
        }
        
        // Check cache first
        $cache_key = 'city_' . md5($ip);
        $cached = $this->cache_get($cache_key);
        
        if ($cached) {
            return $cached;
        }
        
        $api_key = $this->get_setting('openweather_api_key');
        if (empty($api_key)) {
            return false;
        }
        
        // Get coordinates from IP (using a free service)
        $coordinates = $this->get_coordinates_from_ip($ip);
        
        if (!$coordinates) {
            return false;
        }
        
        // Get city from coordinates
        $city_data = $this->get_city_from_coordinates($coordinates['lat'], $coordinates['lon'], $api_key);
        
        if ($city_data) {
            // Cache the result
            $cache_duration = $this->get_setting('cache_duration', 3600);
            $this->cache_set($cache_key, $city_data, $cache_duration);
            
            return $city_data;
        }
        
        return false;
    }
    
    /**
     * Get coordinates from IP
     */
    private function get_coordinates_from_ip($ip) {
        // Use ipapi.co for free IP geolocation
        $url = "http://ipapi.co/{$ip}/json/";
        
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'user-agent' => 'Geo Elementor Plugin'
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['latitude']) && isset($data['longitude'])) {
            return array(
                'lat' => $data['latitude'],
                'lon' => $data['longitude']
            );
        }
        
        return false;
    }
    
    /**
     * Get city from coordinates
     */
    private function get_city_from_coordinates($lat, $lon, $api_key) {
        $url = $this->api_endpoints['reverse_geocoding'] . "?lat={$lat}&lon={$lon}&limit=1&appid={$api_key}";
        
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'user-agent' => 'Geo Elementor Plugin'
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data[0]['name']) && isset($data[0]['country'])) {
            return array(
                'city' => $data[0]['name'],
                'country' => $data[0]['country'],
                'coordinates' => array(
                    'lat' => $lat,
                    'lon' => $lon
                )
            );
        }
        
        return false;
    }
    
    /**
     * Search cities
     */
    private function search_cities($query) {
        $api_key = $this->get_setting('openweather_api_key');
        if (empty($api_key) || empty($query)) {
            return array();
        }
        
        $url = $this->api_endpoints['geocoding'] . "?q=" . urlencode($query) . "&limit=5&appid={$api_key}";
        
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'user-agent' => 'Geo Elementor Plugin'
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        $cities = array();
        if (is_array($data)) {
            foreach ($data as $city) {
                $cities[] = array(
                    'name' => $city['name'],
                    'country' => $city['country'],
                    'state' => isset($city['state']) ? $city['state'] : '',
                    'display' => $city['name'] . ', ' . $city['country']
                );
            }
        }
        
        return $cities;
    }
    
    /**
     * Check if IP is private
     */
    private function is_private_ip($ip) {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    
    /**
     * Add city data to visitor data
     */
    public function add_city_data($visitor_data) {
        $city_data = $this->detect_city($visitor_data['ip']);
        
        if ($city_data) {
            $visitor_data['city'] = $city_data['city'];
            $visitor_data['country'] = $city_data['country'];
            $visitor_data['coordinates'] = $city_data['coordinates'];
        }
        
        return $visitor_data;
    }
    
    /**
     * Add city guard data
     */
    public function add_city_guard_data($guard_data) {
        $visitor_data = $this->get_visitor_data();
        $city_data = $this->detect_city($visitor_data['ip']);
        
        if ($city_data) {
            $guard_data['city'] = $city_data['city'];
            $guard_data['country'] = $city_data['country'];
        }
        
        return $guard_data;
    }
    
    /**
     * Check if city targeting condition is met
     */
    protected function is_targeting_condition_met($settings, $context = array()) {
        if (empty($settings['egp_city_enabled']) || $settings['egp_city_enabled'] !== 'yes') {
            return true; // Not using city targeting
        }
        
        $visitor_data = $this->get_visitor_data();
        $city_data = $this->detect_city($visitor_data['ip']);
        
        if (!$city_data) {
            // Fallback behavior when city detection fails
            $fallback = $settings['egp_city_fallback'] ?? 'hide';
            
            if ($fallback === 'show') {
                return true;
            } elseif ($fallback === 'country') {
                // Use country targeting as fallback
                return true; // Let country targeting handle this
            } else {
                return false; // Hide content
            }
        }
        
        $target_cities = $settings['egp_city_targets'] ?? '';
        if (empty($target_cities)) {
            return true; // No specific cities targeted
        }
        
        $target_list = array_map('trim', explode("\n", $target_cities));
        $visitor_city = strtolower($city_data['city']);
        
        foreach ($target_list as $target) {
            $target = strtolower(trim($target));
            if (empty($target)) continue;
            
            if (strpos($visitor_city, $target) !== false || strpos($target, $visitor_city) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get targeting data for frontend
     */
    protected function get_targeting_data($context = array()) {
        $visitor_data = $this->get_visitor_data();
        $city_data = $this->detect_city($visitor_data['ip']);
        
        return array(
            'city' => $city_data['city'] ?? null,
            'country' => $city_data['country'] ?? null,
            'coordinates' => $city_data['coordinates'] ?? null,
            'detected' => !empty($city_data)
        );
    }
}