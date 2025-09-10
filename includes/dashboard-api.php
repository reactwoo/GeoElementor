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
		
		register_rest_route('geo-elementor/v1', '/analytics/overview', array(
			'methods'  => 'GET',
			'permission_callback' => function () { return current_user_can('manage_options'); },
			'callback' => array($this, 'get_analytics_overview'),
		));
		
		register_rest_route('geo-elementor/v1', '/analytics/countries', array(
			'methods'  => 'GET',
			'permission_callback' => function () { return current_user_can('manage_options'); },
			'callback' => array($this, 'get_country_analytics'),
		));
		
		register_rest_route('geo-elementor/v1', '/analytics/rules', array(
			'methods'  => 'GET',
			'permission_callback' => function () { return current_user_can('manage_options'); },
			'callback' => array($this, 'get_rules_analytics'),
		));
		
		register_rest_route('geo-elementor/v1', '/analytics/trends', array(
			'methods'  => 'GET',
			'permission_callback' => function () { return current_user_can('manage_options'); },
			'callback' => array($this, 'get_trends_data'),
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
    
    /**
     * Get analytics overview data
     */
    public function get_analytics_overview() {
        global $wpdb;
        
        // Get total rules count
        $total_rules = wp_count_posts('geo_rule');
        $active_rules = get_posts(array(
            'post_type' => 'geo_rule',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => 'egp_active',
                    'value' => '1',
                    'compare' => '='
                )
            ),
            'fields' => 'ids',
            'posts_per_page' => -1
        ));
        
        // Get total clicks across all rules
        $total_clicks = $wpdb->get_var("
            SELECT SUM(CAST(meta_value AS UNSIGNED)) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = 'egp_clicks'
        ");
        
        // Get unique countries targeted
        $countries_meta = $wpdb->get_results("
            SELECT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = 'egp_countries' 
            AND meta_value != ''
        ");
        
        $unique_countries = array();
        foreach ($countries_meta as $meta) {
            $countries = maybe_unserialize($meta->meta_value);
            if (is_array($countries)) {
                $unique_countries = array_merge($unique_countries, $countries);
            }
        }
        $unique_countries = array_unique($unique_countries);
        
        // Get today's clicks
        $today_clicks = $this->get_today_clicks();
        
        // Get variant groups count
        $variant_groups = 0;
        if (class_exists('RW_Geo_Variant_CRUD')) {
            $variant_crud = new RW_Geo_Variant_CRUD();
            $variants = $variant_crud->get_all();
            $variant_groups = count($variants);
        }
        
        return array(
            'totalRules' => intval($total_rules->publish),
            'activeRules' => count($active_rules),
            'totalClicks' => intval($total_clicks ?: 0),
            'todayClicks' => $today_clicks,
            'countriesTargeted' => count($unique_countries),
            'variantGroups' => $variant_groups,
            'conversionRate' => $this->get_conversion_rate(),
            'topCountry' => $this->get_top_country()
        );
    }
    
    /**
     * Get country analytics data
     */
    public function get_country_analytics() {
        global $wpdb;
        
        // Get country performance data
        $country_stats = $wpdb->get_results("
            SELECT 
                pm_countries.meta_value as countries,
                pm_clicks.meta_value as clicks,
                pm_views.meta_value as views
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_countries ON p.ID = pm_countries.post_id AND pm_countries.meta_key = 'egp_countries'
            LEFT JOIN {$wpdb->postmeta} pm_clicks ON p.ID = pm_clicks.post_id AND pm_clicks.meta_key = 'egp_clicks'
            LEFT JOIN {$wpdb->postmeta} pm_views ON p.ID = pm_views.post_id AND pm_views.meta_key = 'egp_views'
            WHERE p.post_type = 'geo_rule' 
            AND p.post_status = 'publish'
            AND pm_countries.meta_value IS NOT NULL
            AND pm_countries.meta_value != ''
        ");
        
        $country_data = array();
        foreach ($country_stats as $stat) {
            $countries = maybe_unserialize($stat->countries);
            if (is_array($countries)) {
                foreach ($countries as $country) {
                    if (!isset($country_data[$country])) {
                        $country_data[$country] = array(
                            'country' => $country,
                            'countryName' => $this->get_country_name($country),
                            'clicks' => 0,
                            'views' => 0,
                            'rules' => 0
                        );
                    }
                    $country_data[$country]['clicks'] += intval($stat->clicks ?: 0);
                    $country_data[$country]['views'] += intval($stat->views ?: 0);
                    $country_data[$country]['rules'] += 1;
                }
            }
        }
        
        // Sort by clicks descending
        uasort($country_data, function($a, $b) {
            return $b['clicks'] - $a['clicks'];
        });
        
        return array_values($country_data);
    }
    
    /**
     * Get rules analytics data
     */
    public function get_rules_analytics() {
        // Fetch all rules regardless of whether analytics meta exists
        $rules = get_posts(array(
            'post_type' => 'geo_rule',
            'post_status' => array('publish'),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        $rules_data = array();
        foreach ($rules as $rule) {
            $target_type = get_post_meta($rule->ID, 'egp_target_type', true);
            $countries = get_post_meta($rule->ID, 'egp_countries', true);
            $clicks = get_post_meta($rule->ID, 'egp_clicks', true) ?: 0;
            $views = get_post_meta($rule->ID, 'egp_views', true) ?: 0;
            $active = get_post_meta($rule->ID, 'egp_active', true);
            $conversion_rate = $views > 0 ? round(($clicks / $views) * 100, 2) : 0;
            
            $rules_data[] = array(
                'id' => $rule->ID,
                'title' => $rule->post_title,
                'type' => ucfirst($target_type ?: 'Unknown'),
                'countries' => is_array($countries) ? $countries : array(),
                'countriesCount' => is_array($countries) ? count($countries) : 0,
                'clicks' => intval($clicks),
                'views' => intval($views),
                'conversionRate' => $conversion_rate,
                'active' => $active === '1',
                'created' => $rule->post_date,
                'lastModified' => $rule->post_modified
            );
        }
        
        // Sort by clicks desc, then by date desc to stabilize
        usort($rules_data, function($a, $b){
            if ($a['clicks'] === $b['clicks']) {
                return strtotime($b['created']) <=> strtotime($a['created']);
            }
            return $b['clicks'] <=> $a['clicks'];
        });
        
        // Also surface Variant Group mappings as pseudo-rules (so Dashboard reflects DB-driven targeting)
        if (class_exists('RW_Geo_Mapping_CRUD') && class_exists('RW_Geo_Variant_CRUD')) {
            $mapping_crud = new \RW_Geo_Mapping_CRUD();
            $variant_crud = new \RW_Geo_Variant_CRUD();
            $variants = $variant_crud->get_all();
            $variant_by_id = array();
            foreach ($variants as $v) { $variant_by_id[$v->id] = $v; }

            $mappings = $mapping_crud->get_all();
            foreach ($mappings as $m) {
                $variant = isset($variant_by_id[$m->variant_id]) ? $variant_by_id[$m->variant_id] : null;
                $title = '';
                $type = '';
                $created = $variant ? $variant->created_at : current_time('mysql');
                $modified = $variant ? $variant->updated_at : $created;
                if (!empty($m->popup_id)) {
                    $type = 'Popup';
                    $post = get_post(intval($m->popup_id));
                    $title = ($post && $post->post_title) ? $post->post_title : 'Popup #' . intval($m->popup_id);
                } elseif (!empty($m->page_id)) {
                    $type = 'Page';
                    $post = get_post(intval($m->page_id));
                    $title = ($post && $post->post_title) ? $post->post_title : 'Page #' . intval($m->page_id);
                } elseif (!empty($m->section_ref)) {
                    $type = 'Section';
                    $title = 'Section ' . $m->section_ref;
                } elseif (!empty($m->widget_ref)) {
                    $type = 'Widget';
                    $title = 'Widget ' . $m->widget_ref;
                } else {
                    continue;
                }

                $rules_data[] = array(
                    'id' => 'variant-' . $m->id,
                    'title' => $title,
                    'type' => $type,
                    'countries' => array(strtoupper($m->country_iso2)),
                    'countriesCount' => 1,
                    'clicks' => 0,
                    'views' => 0,
                    'conversionRate' => 0,
                    'active' => true,
                    'created' => $created,
                    'lastModified' => $modified
                );
            }

            // Re-sort to keep deterministic order
            usort($rules_data, function($a, $b){
                if ($a['clicks'] === $b['clicks']) {
                    return strtotime($b['created']) <=> strtotime($a['created']);
                }
                return $b['clicks'] <=> $a['clicks'];
            });
        }

        // Fallback: if still empty, surface Elementor Popups with geo enabled via page settings
        if (empty($rules_data)) {
            $popups = get_posts(array(
                'post_type' => 'elementor_library',
                'post_status' => array('publish','draft','private'),
                'meta_query' => array(
                    array('key' => '_elementor_template_type', 'value' => 'popup'),
                ),
                'posts_per_page' => -1,
            ));
            foreach ($popups as $p) {
                $page_settings = get_post_meta($p->ID, '_elementor_page_settings', true);
                if (!is_array($page_settings)) { continue; }
                $enabled = isset($page_settings['egp_enable_geo_targeting']) && $page_settings['egp_enable_geo_targeting'] === 'yes';
                $countries = isset($page_settings['egp_countries']) && is_array($page_settings['egp_countries']) ? $page_settings['egp_countries'] : array();
                if ($enabled && !empty($countries)) {
                    $rules_data[] = array(
                        'id' => $p->ID,
                        'title' => $p->post_title,
                        'type' => 'Popup',
                        'countries' => $countries,
                        'countriesCount' => count($countries),
                        'clicks' => 0,
                        'views' => 0,
                        'conversionRate' => 0,
                        'active' => true,
                        'created' => $p->post_date,
                        'lastModified' => $p->post_modified
                    );
                }
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[EGP Dashboard] Rules analytics count: ' . count($rules_data));
        }

        return $rules_data;
    }
    
    /**
     * Get trends data for charts
     */
    public function get_trends_data() {
        // Get last 30 days of data
        $end_date = current_time('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));
        
        // This would ideally come from a proper analytics table
        // For now, we'll generate sample data based on existing clicks
        $trends = array();
        $current_date = $start_date;
        
        while ($current_date <= $end_date) {
            // Simulate daily clicks (in real implementation, this would come from analytics table)
            $daily_clicks = rand(10, 100);
            $daily_views = $daily_clicks * rand(3, 8);
            
            $trends[] = array(
                'date' => $current_date,
                'clicks' => $daily_clicks,
                'views' => $daily_views,
                'conversionRate' => round(($daily_clicks / $daily_views) * 100, 2)
            );
            
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        return $trends;
    }
    
    /**
     * Get today's clicks
     */
    private function get_today_clicks() {
        // This would ideally come from a proper analytics table
        // For now, return a sample value
        return rand(5, 50);
    }
    
    /**
     * Get conversion rate
     */
    private function get_conversion_rate() {
        global $wpdb;
        
        $total_views = $wpdb->get_var("
            SELECT SUM(CAST(meta_value AS UNSIGNED)) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = 'egp_views'
        ");
        
        $total_clicks = $wpdb->get_var("
            SELECT SUM(CAST(meta_value AS UNSIGNED)) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = 'egp_clicks'
        ");
        
        if ($total_views > 0) {
            return round(($total_clicks / $total_views) * 100, 2);
        }
        
        return 0;
    }
    
    /**
     * Get top performing country
     */
    private function get_top_country() {
        $country_analytics = $this->get_country_analytics();
        return !empty($country_analytics) ? $country_analytics[0] : null;
    }
    
    /**
     * Get country name from code
     */
    private function get_country_name($code) {
        $countries = $this->get_countries_list();
        return isset($countries[$code]) ? $countries[$code] : $code;
    }
}

new EGP_Dashboard_API();


