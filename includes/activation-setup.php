<?php
/**
 * Activation Setup Script
 * Creates default variant groups and demonstrates the fallback system
 * 
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Setup default variant groups on activation
 */
function rw_geo_setup_default_variants() {
    // Only run once
    if (get_option('rw_geo_default_variants_created')) {
        return;
    }
    
    // Check if our classes are available
    if (!class_exists('RW_Geo_Variant_CRUD') || !class_exists('RW_Geo_Mapping_CRUD')) {
        return;
    }
    
    $variant_crud = new RW_Geo_Variant_CRUD();
    $mapping_crud = new RW_Geo_Mapping_CRUD();
    
    // Create default homepage variant group
    $homepage_variant = $variant_crud->create(array(
        'name' => 'Homepage',
        'slug' => 'homepage',
        'type_mask' => RW_GEO_TYPE_PAGE | RW_GEO_TYPE_POPUP, // Page + Popup
        'options' => array(
            'soft_redirect' => true,
            'show_selector' => true,
            'respect_cookie' => true,
            'skip_bots' => true,
            'cookie_ttl' => 60
        )
    ));
    
    if (!is_wp_error($homepage_variant)) {
        // Get the homepage
        $homepage = get_option('page_on_front') ? get_page(get_option('page_on_front')) : null;
        
        if (!$homepage) {
            // Try to get any page
            $pages = get_pages(array('numberposts' => 1));
            $homepage = !empty($pages) ? $pages[0] : null;
        }
        
        if ($homepage) {
            // Set as default page
            $variant_crud->update($homepage_variant, array(
                'default_page_id' => $homepage->ID
            ));
            
            // Create some example country mappings
            $example_countries = array('US', 'GB', 'CA', 'AU');
            
            foreach ($example_countries as $country) {
                $mapping_crud->create(array(
                    'variant_id' => $homepage_variant,
                    'country_iso2' => $country,
                    'page_id' => $homepage->ID, // Use same page for now
                    'popup_id' => null
                ));
            }
        }
    }
    
    // Create a promo banner variant group (for sections/widgets)
    $promo_variant = $variant_crud->create(array(
        'name' => 'Promo Banner',
        'slug' => 'promo-banner',
        'type_mask' => RW_GEO_TYPE_SECTION | RW_GEO_TYPE_WIDGET, // Section + Widget
        'options' => array(
            'soft_redirect' => false, // No redirects for sections/widgets
            'show_selector' => true,
            'respect_cookie' => true,
            'skip_bots' => true,
            'cookie_ttl' => 60
        )
    ));
    
    if (!is_wp_error($promo_variant)) {
        // Create example country mappings for promo banner
        $promo_countries = array('US', 'GB');
        
        foreach ($promo_countries as $country) {
            $mapping_crud->create(array(
                'variant_id' => $promo_variant,
                'country_iso2' => $country,
                'section_ref' => 'promo_' . strtolower($country),
                'widget_ref' => 'promo_widget_' . strtolower($country)
            ));
        }
    }
    
    // Mark as completed
    update_option('rw_geo_default_variants_created', true);
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[RW Geo] Default variant groups created successfully');
    }
}

/**
 * Clean up on deactivation
 */
function rw_geo_cleanup_default_variants() {
    delete_option('rw_geo_default_variants_created');
}

// Hook into activation
add_action('rw_geo_after_activation', 'rw_geo_setup_default_variants');

// Hook into deactivation
add_action('rw_geo_after_deactivation', 'rw_geo_cleanup_default_variants');
