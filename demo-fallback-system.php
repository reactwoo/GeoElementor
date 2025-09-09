<?php
/**
 * Demo: Fallback System Demonstration
 * Shows how the geo-targeting fallback system works
 * 
 * @package ElementorGeoPopup
 * @since 1.0.0
 * 
 * This file demonstrates the fallback system functionality.
 * Include it in your theme or run it to see examples.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Demo: Show how fallback system works
 */
function rw_geo_demo_fallback_system() {
    if (!class_exists('RW_Geo_Router')) {
        echo '<div class="notice notice-error"><p>Geo Router not available. Please ensure the plugin is properly loaded.</p></div>';
        return;
    }
    
    $router = RW_Geo_Router::get_instance();
    
    echo '<div class="wrap">';
    echo '<h2>🌍 Geo Fallback System Demo</h2>';
    echo '<p>This demonstration shows how the fallback system works for different visitor scenarios.</p>';
    
    // Simulate different visitor scenarios
    $scenarios = array(
        'US Visitor' => 'US',
        'UK Visitor' => 'GB', 
        'Canadian Visitor' => 'CA',
        'Unknown Country' => null,
        'Bot/Crawler' => 'BOT'
    );
    
    echo '<div class="demo-scenarios">';
    echo '<h3>Visitor Scenarios</h3>';
    
    foreach ($scenarios as $scenario => $country) {
        echo '<div class="scenario-box">';
        echo '<h4>' . esc_html($scenario) . '</h4>';
        
        if ($country === 'BOT') {
            echo '<p><strong>Bot Detection:</strong> Will skip redirects and show global content</p>';
            echo '<p><strong>Result:</strong> Global homepage + Global popup</p>';
        } elseif ($country) {
            echo '<p><strong>Country:</strong> ' . esc_html($country) . '</p>';
            
            // Get variant group
            $variant_crud = new RW_Geo_Variant_CRUD();
            $variant = $variant_crud->get_by_slug('homepage');
            
            if ($variant) {
                // Get mapping for this country
                $mapping_crud = new RW_Geo_Mapping_CRUD();
                $mapping = $mapping_crud->get_by_variant_country($variant->id, $country);
                
                if ($mapping) {
                    echo '<p><strong>Result:</strong> Country-specific content found!</p>';
                    echo '<ul>';
                    if ($mapping->page_id) {
                        $page_title = get_the_title($mapping->page_id);
                        echo '<li>Page: ' . esc_html($page_title) . '</li>';
                    }
                    if ($mapping->popup_id) {
                        $popup_title = get_the_title($mapping->popup_id);
                        echo '<li>Popup: ' . esc_html($popup_title) . '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p><strong>Result:</strong> No country-specific content, using fallback</p>';
                    echo '<ul>';
                    if ($variant->default_page_id) {
                        $page_title = get_the_title($variant->default_page_id);
                        echo '<li>Page: ' . esc_html($page_title) . ' (Global)</li>';
                    }
                    if ($variant->default_popup_id) {
                        $popup_title = get_the_title($variant->default_popup_id);
                        echo '<li>Popup: ' . esc_html($popup_title) . ' (Global)</li>';
                    }
                    echo '</ul>';
                }
            } else {
                echo '<p><strong>Result:</strong> No variant group configured</p>';
            }
        } else {
            echo '<p><strong>Country:</strong> Unknown/Detection failed</p>';
            echo '<p><strong>Result:</strong> Using global fallback content</p>';
        }
        
        echo '</div>';
    }
    
    echo '</div>';
    
    // Show current variant groups
    echo '<div class="demo-variants">';
    echo '<h3>Configured Variant Groups</h3>';
    
    $variant_crud = new RW_Geo_Variant_CRUD();
    $variants = $variant_crud->get_all();
    
    if (empty($variants)) {
        echo '<p>No variant groups configured yet. <a href="' . admin_url('admin.php?page=geo-elementor-variants') . '">Create your first one</a>!</p>';
    } else {
        foreach ($variants as $variant) {
            echo '<div class="variant-box">';
            echo '<h4>' . esc_html($variant->name) . ' (' . esc_html($variant->slug) . ')</h4>';
            
            $types = array();
            if ($variant->type_mask & RW_GEO_TYPE_PAGE) $types[] = 'Page';
            if ($variant->type_mask & RW_GEO_TYPE_POPUP) $types[] = 'Popup';
            if ($variant->type_mask & RW_GEO_TYPE_SECTION) $types[] = 'Section';
            if ($variant->type_mask & RW_GEO_TYPE_WIDGET) $types[] = 'Widget';
            
            echo '<p><strong>Types:</strong> ' . implode(', ', $types) . '</p>';
            
            // Show defaults
            echo '<p><strong>Defaults:</strong></p>';
            echo '<ul>';
            if ($variant->default_page_id) {
                $page_title = get_the_title($variant->default_page_id);
                echo '<li>Page: ' . esc_html($page_title) . '</li>';
            }
            if ($variant->default_popup_id) {
                $popup_title = get_the_title($variant->default_popup_id);
                echo '<li>Popup: ' . esc_html($popup_title) . '</li>';
            }
            echo '</ul>';
            
            // Show country mappings
            $mapping_crud = new RW_Geo_Mapping_CRUD();
            $mappings = $mapping_crud->get_by_variant($variant->id);
            
            if (!empty($mappings)) {
                echo '<p><strong>Country Mappings:</strong></p>';
                echo '<ul>';
                foreach ($mappings as $mapping) {
                    echo '<li>' . esc_html($mapping->country_iso2) . ': ';
                    if ($mapping->page_id) {
                        $page_title = get_the_title($mapping->page_id);
                        echo 'Page: ' . esc_html($page_title);
                    }
                    if ($mapping->popup_id) {
                        $popup_title = get_the_title($mapping->popup_id);
                        echo ' Popup: ' . esc_html($popup_title);
                    }
                    echo '</li>';
                }
                echo '</ul>';
            }
            
            echo '</div>';
        }
    }
    
    echo '</div>';
    
    // Show routing logic
    echo '<div class="demo-routing">';
    echo '<h3>Routing Logic</h3>';
    echo '<div class="routing-flow">';
    echo '<ol>';
    echo '<li><strong>Visitor arrives</strong> - Geo detection runs</li>';
    echo '<li><strong>Country identified</strong> - US, GB, CA, etc.</li>';
    echo '<li><strong>Variant group selected</strong> - Based on current page/route</li>';
    echo '<li><strong>Mapping lookup</strong> - Find country-specific content</li>';
    echo '<li><strong>Content resolution:</strong>';
    echo '<ul>';
    echo '<li>✅ <strong>Country match found</strong> → Show country-specific content</li>';
    echo '<li>❌ <strong>No country match</strong> → Fall back to global defaults</li>';
    echo '<li>🤖 <strong>Bot detected</strong> → Skip redirects, show global content</li>';
    echo '</ul>';
    echo '</li>';
    echo '<li><strong>Page redirect</strong> - If enabled and page mismatch</li>';
    echo '<li><strong>Content rendering</strong> - Show appropriate popups/sections/widgets</li>';
    echo '</ol>';
    echo '</div>';
    echo '</div>';
    
    // Show current settings
    echo '<div class="demo-settings">';
    echo '<h3>Current Settings</h3>';
    
    $settings = get_option('rw_geo_settings', array());
    
    echo '<table class="form-table">';
    echo '<tr><th>MaxMind Database</th><td>' . (!empty($settings['maxmind']['db_path']) ? 'Configured' : 'Not configured') . '</td></tr>';
    echo '<tr><th>Region Selector</th><td>' . (!empty($settings['selector']['enabled']) ? 'Enabled' : 'Disabled') . '</td></tr>';
    echo '<tr><th>Cookie TTL</th><td>' . esc_html($settings['selector']['ttl_days'] ?? 60) . ' days</td></tr>';
    echo '<tr><th>Skip Bots</th><td>' . (!empty($settings['bots']['skip_redirect']) ? 'Yes' : 'No') . '</td></tr>';
    echo '<tr><th>QA Override</th><td>' . (!empty($settings['qa']['enable_force_param']) ? 'Enabled' : 'Disabled') . '</td></tr>';
    echo '</table>';
    
    echo '</div>';
    
    echo '</div>';
    
    // Add some CSS for the demo
    echo '<style>
        .demo-scenarios, .demo-variants, .demo-routing, .demo-settings {
            margin: 20px 0;
            padding: 20px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .scenario-box, .variant-box {
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin: 10px 0;
        }
        
        .scenario-box h4, .variant-box h4 {
            margin-top: 0;
            color: #0073aa;
        }
        
        .routing-flow ol {
            margin-left: 20px;
        }
        
        .routing-flow ul {
            margin: 10px 0 10px 20px;
        }
        
        .routing-flow li {
            margin-bottom: 8px;
        }
        
        .form-table th {
            width: 150px;
        }
    </style>';
}

// Hook to display demo on admin page
add_action('admin_notices', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'geo-elementor-variants' && isset($_GET['demo'])) {
        rw_geo_demo_fallback_system();
    }
});

// Add demo button to variant groups page
add_action('admin_footer', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'geo-elementor-variants') {
        echo '<script>
            jQuery(document).ready(function($) {
                $(".wrap h1").after(\'<p><a href="?page=geo-elementor-variants&demo=1" class="button button-secondary">View Fallback System Demo</a></p>\');
            });
        </script>';
    }
});
