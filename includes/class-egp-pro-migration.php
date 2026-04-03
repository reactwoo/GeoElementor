<?php
/**
 * Free -> Pro routing migration utility.
 *
 * Reads **ReactWoo Geo Core** page routing metadata via `RWGC_Routing::get_page_route_config()`
 * (same `_rwgc_route_*` post meta the Core engine uses). Geo Core resolves legacy storage into
 * bundles internally (`RWGC_Legacy_Route_Mapper`); this class only maps those configs into
 * Geo Elementor Pro variant groups — it does not replace Core migrations.
 *
 * @package ElementorGeoPopup
 */

if (!defined('ABSPATH')) {
    exit;
}

class EGP_Pro_Migration {

    const OPTION_MIGRATED_MASTERS = 'egp_migrated_master_ids';

    /**
     * Initialize migration hooks.
     *
     * @return void
     */
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'maybe_run'));
    }

    /**
     * Migrate free Master/Secondary mappings to first Pro group mappings.
     *
     * @return void
     */
    public static function maybe_run() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        if (!class_exists('RWGC_Routing') || !class_exists('RW_Geo_Variant_CRUD') || !class_exists('RW_Geo_Mapping_CRUD')) {
            return;
        }

        $settings = get_option('rw_geo_settings', array());
        $enabled = isset($settings['routing']['auto_migrate_from_free']) ? (bool) $settings['routing']['auto_migrate_from_free'] : true;
        if (!$enabled) {
            return;
        }

        $migrated = get_option(self::OPTION_MIGRATED_MASTERS, array());
        if (!is_array($migrated)) {
            $migrated = array();
        }

        $variant_crud = new RW_Geo_Variant_CRUD();
        $mapping_crud = new RW_Geo_Mapping_CRUD();

        $secondary_pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'any',
            'posts_per_page' => 300,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_rwgc_route_role',
                    'value' => 'variant',
                    'compare' => '=',
                ),
            ),
        ));

        if (empty($secondary_pages)) {
            return;
        }

        $grouped = array();
        foreach ($secondary_pages as $secondary_id) {
            $cfg = RWGC_Routing::get_page_route_config((int) $secondary_id);
            $master_id = !empty($cfg['master_page_id']) ? intval($cfg['master_page_id']) : 0;
            $country = !empty($cfg['country_iso2']) ? strtoupper((string) $cfg['country_iso2']) : '';
            if ($master_id <= 0 || !$country) {
                continue;
            }
            if (!isset($grouped[$master_id])) {
                $grouped[$master_id] = array();
            }
            $grouped[$master_id][$country] = intval($secondary_id);
        }

        foreach ($grouped as $master_id => $country_targets) {
            if (in_array($master_id, $migrated, true)) {
                continue;
            }

            $master_title = get_the_title($master_id);
            $slug = 'master-' . $master_id . '-group';
            $existing = $variant_crud->get_by_slug($slug);

            $variant_id = $existing ? intval($existing->id) : $variant_crud->create(array(
                'name' => sprintf(__('Master: %s', 'elementor-geo-popup'), $master_title ? $master_title : ('#' . $master_id)),
                'slug' => $slug,
                'type_mask' => RW_GEO_TYPE_PAGE | RW_GEO_TYPE_POPUP,
                'master_page_id' => $master_id,
                'is_active' => 1,
                'priority' => 50,
                'default_page_id' => $master_id,
                'options' => array(
                    'soft_redirect' => true,
                    'managed_by_pro' => true,
                    'migrated_from_free' => true,
                ),
            ));

            if (is_wp_error($variant_id) || !$variant_id) {
                continue;
            }

            foreach ($country_targets as $country => $secondary_page_id) {
                $existing_map = $mapping_crud->get_by_variant_country($variant_id, $country);
                if ($existing_map) {
                    continue;
                }
                $mapping_crud->create(array(
                    'variant_id' => $variant_id,
                    'country_iso2' => $country,
                    'page_id' => $secondary_page_id,
                ));
            }

            $migrated[] = $master_id;
        }

        update_option(self::OPTION_MIGRATED_MASTERS, array_values(array_unique(array_map('intval', $migrated))));
    }
}

