<?php
/**
 * Geolocation Detection
 * 
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Geolocation Detection Class
 */
class EGP_Geo_Detect {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_footer', array($this, 'inject_geo_popup_script'));
        add_action('wp_ajax_egp_get_visitor_country', array($this, 'ajax_get_visitor_country'));
        add_action('wp_ajax_nopriv_egp_get_visitor_country', array($this, 'ajax_get_visitor_country'));
    }
    
    /**
     * Inject geo popup script
     */
    public function inject_geo_popup_script() {
        // Only inject on frontend pages
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        
        // Check if we have any geo-targeted popups
        if (!$this->has_geo_targeted_popups()) {
            return;
        }
        
        // Get visitor's country
        $country = $this->get_visitor_country();
        
        if (!$country) {
            return;
        }
        
        // Get matching popup
        $popup_id = $this->get_matching_popup($country);
        
        if (!$popup_id) {
            return;
        }
        
        // Inject the popup trigger script
        $this->render_popup_script($popup_id, $country);
    }
    
    /**
     * Check if there are any geo-targeted popups
     */
    private function has_geo_targeted_popups() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'egp_popup_countries';
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE enabled = 1");
        
        return $count > 0;
    }
    
    /**
     * Get visitor's country
     */
    public function get_visitor_country() {
        // Check for cached result first
        $cached_country = wp_cache_get('egp_visitor_country_' . $this->get_visitor_ip(), 'egp_geo');
        
        if ($cached_country !== false) {
            return $cached_country;
        }
        
        // Get visitor's IP
        $ip = $this->get_visitor_ip();
        
        if (!$ip || $this->is_private_ip($ip)) {
            return false;
        }
        
        // Look up country using MaxMind database
        $country = $this->lookup_country($ip);
        
        if ($country) {
            // Cache the result for 1 hour
            wp_cache_set('egp_visitor_country_' . $ip, $country, 'egp_geo', HOUR_IN_SECONDS);
        }
        
        return $country;
    }
    
    /**
     * Get visitor's IP address
     */
    private function get_visitor_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
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
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    /**
     * Check if IP is private
     */
    private function is_private_ip($ip) {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    
    /**
     * Look up country using MaxMind database
     */
    private function lookup_country($ip) {
        $database_path = get_option('egp_database_path');
        
        if (!$database_path || !file_exists($database_path)) {
            if (get_option('egp_debug_mode')) {
                error_log('EGP: MaxMind database not found at ' . $database_path);
            }
            return false;
        }
        
        try {
            // Check if geoip2 library is available
            if (!class_exists('GeoIp2\Database\Reader')) {
                if (get_option('egp_debug_mode')) {
                    error_log('EGP: GeoIP2 library not available');
                }
                return false;
            }
            
            $reader = new GeoIp2\Database\Reader($database_path);
            $record = $reader->country($ip);
            
            $country_code = $record->country->isoCode;
            
            if (get_option('egp_debug_mode')) {
                error_log('EGP: IP ' . $ip . ' resolved to country ' . $country_code);
            }
            
            $reader->close();
            
            return $country_code;
            
        } catch (Exception $e) {
            if (get_option('egp_debug_mode')) {
                error_log('EGP: Error looking up country for IP ' . $ip . ': ' . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Get matching popup for country
     */
    private function get_matching_popup($country) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'egp_popup_countries';
        
        // Find popups that target this country
        $popups = $wpdb->get_results($wpdb->prepare(
            "SELECT popup_id, countries, fallback_behavior FROM $table_name 
             WHERE enabled = 1 AND JSON_CONTAINS(countries, %s)",
            json_encode($country)
        ));
        
        if (empty($popups)) {
            // Check fallback behavior
            return $this->get_fallback_popup();
        }
        
        // Return the first matching popup
        return $popups[0]->popup_id;
    }
    
    /**
     * Get fallback popup based on global settings
     */
    private function get_fallback_popup() {
        $fallback_behavior = get_option('egp_fallback_behavior', 'show_to_all');
        
        switch ($fallback_behavior) {
            case 'show_default':
                return get_option('egp_default_popup_id', 0);
            case 'show_to_all':
                // Return a random popup or the first available
                return $this->get_random_popup();
            case 'show_to_none':
            default:
                return 0;
        }
    }
    
    /**
     * Get random popup for fallback
     */
    private function get_random_popup() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'egp_popup_countries';
        
        $popup_id = $wpdb->get_var("SELECT popup_id FROM $table_name WHERE enabled = 1 ORDER BY RAND() LIMIT 1");
        
        return $popup_id ?: 0;
    }
    
    /**
     * Render popup trigger script
     */
    private function render_popup_script($popup_id, $country) {
        if (!$popup_id) {
            return;
        }
        
        // Check if popup exists and is published
        $popup = get_post($popup_id);
        if (!$popup || $popup->post_status !== 'publish') {
            return;
        }
        
        ?>
        <script type="text/javascript">
        (function() {
            'use strict';
            
            // Wait for Elementor to be ready
            function triggerGeoPopup() {
                if (typeof elementorProFrontend !== 'undefined' && 
                    elementorProFrontend.modules && 
                    elementorProFrontend.modules.popup) {
                    
                    // Add a small delay to ensure page is fully loaded
                    setTimeout(function() {
                        elementorProFrontend.modules.popup.showPopup({
                            id: <?php echo intval($popup_id); ?>,
                            isEvent: false
                        });
                        
                        <?php if (get_option('egp_debug_mode')) : ?>
                        console.log('EGP: Triggering popup <?php echo intval($popup_id); ?> for country <?php echo esc_js($country); ?>');
                        <?php endif; ?>
                    }, 1000);
                    
                } else {
                    // Retry after a short delay
                    setTimeout(triggerGeoPopup, 500);
                }
            }
            
            // Start the process when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', triggerGeoPopup);
            } else {
                triggerGeoPopup();
            }
        })();
        </script>
        <?php
    }
    
    /**
     * AJAX get visitor country
     */
    public function ajax_get_visitor_country() {
        check_ajax_referer('egp_geo_nonce', 'nonce');
        
        $country = $this->get_visitor_country();
        
        if ($country) {
            wp_send_json_success(array('country' => $country));
        } else {
            wp_send_json_error(__('Could not determine country', 'elementor-geo-popup'));
        }
    }
    
    /**
     * Get country name from code
     */
    public static function get_country_name($country_code) {
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
        );
        
        return isset($countries[$country_code]) ? $countries[$country_code] : $country_code;
    }
}



