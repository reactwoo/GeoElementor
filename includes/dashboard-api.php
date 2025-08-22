<?php
// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class EGP_Dashboard_API {
	public function __construct() {
		add_action('rest_api_init', array($this, 'register_routes'));
	}

	public function register_routes() {
		register_rest_route('geo-elementor/v1', '/dashboard', array(
			'methods'  => 'GET',
			'permission_callback' => function () { return current_user_can('manage_options'); },
			'callback' => array($this, 'get_dashboard_data'),
		));
	}

	    public function get_dashboard_data() {
        // Get real data from geo rules
        $geo_rules = $this->get_geo_rules_data();
        $analytics = $this->get_analytics_data();
        
        return array(
            'topLocations' => $analytics['topLocations'],
            'rulesUsage' => $analytics['rulesUsage'],
            'engagement' => $analytics['engagement'],
            'filters' => array(
                'types' => array('All','Page','Popup','Section','Form'),
                'countries' => array_merge(array('All'), array_keys($this->get_countries_list())),
            ),
            'items' => $geo_rules,
        );
    }
    
    /**
     * Get geo rules data for dashboard
     */
    private function get_geo_rules_data() {
        $args = array(
            'post_type' => 'geo_rule',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        $rules = get_posts($args);
        $formatted_rules = array();
        
        foreach ($rules as $rule) {
            $target_type = get_post_meta($rule->ID, 'egp_target_type', true);
            $countries = get_post_meta($rule->ID, 'egp_countries', true);
            $active = get_post_meta($rule->ID, 'egp_active', true);
            
            if (!is_array($countries)) {
                $countries = array();
            }
            
            $formatted_rules[] = array(
                'id' => $rule->ID,
                'title' => $rule->post_title,
                'type' => ucfirst($target_type),
                'country' => !empty($countries) ? implode(', ', $countries) : 'None',
                'active' => $active === '1'
            );
        }
        
        return $formatted_rules;
    }
    
    /**
     * Get analytics data for dashboard
     */
    private function get_analytics_data() {
        // For now, return mock data - will be replaced with real analytics
        return array(
            'topLocations' => array(
                array('country' => 'US', 'visits' => 1240),
                array('country' => 'GB', 'visits' => 830),
                array('country' => 'DE', 'visits' => 560),
            ),
            'rulesUsage' => array(
                array('type' => 'Pages', 'count' => $this->count_rules_by_type('page')),
                array('type' => 'Popups', 'count' => $this->count_rules_by_type('popup')),
                array('type' => 'Sections', 'count' => $this->count_rules_by_type('section')),
                array('type' => 'Forms', 'count' => $this->count_rules_by_type('form')),
            ),
            'engagement' => array(
                'labels' => array('Mon','Tue','Wed','Thu','Fri','Sat','Sun'),
                'byCountry' => array(
                    'US' => array(10,14,20,25,22,18,15),
                    'GB' => array(6,8,12,14,13,9,7),
                    'DE' => array(5,7,9,11,10,8,6),
                ),
            ),
        );
    }
    
    /**
     * Count rules by type
     */
    private function count_rules_by_type($type) {
        $args = array(
            'post_type' => 'geo_rule',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => 'egp_target_type',
                    'value' => $type,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        
        $rules = get_posts($args);
        return count($rules);
    }
    
    /**
     * Get countries list
     */
    private function get_countries_list() {
        return array(
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'DE' => 'Germany',
            'FR' => 'France',
            'AU' => 'Australia',
            'JP' => 'Japan',
            'BR' => 'Brazil',
            'IN' => 'India',
            'CN' => 'China',
        );
    }
}

new EGP_Dashboard_API();


