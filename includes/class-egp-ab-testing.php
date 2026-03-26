<?php
/**
 * A/B testing contracts for GeoElementor variant groups.
 *
 * @package ElementorGeoPopup
 */

if (!defined('ABSPATH')) {
    exit;
}

class EGP_AB_Testing {

    const COOKIE_NAME = 'egp_ab_assignments';

    /**
     * Bootstrap hooks.
     *
     * @return void
     */
    public static function init() {
        add_filter('egp_ab_assignment_target', array(__CLASS__, 'maybe_assign_bucket_target'), 10, 4);
    }

    /**
     * Return target overridden by weighted experiment assignment when enabled.
     *
     * options contract on variant:
     * options['ab_test'] = array(
     *   'enabled' => true|false,
     *   'experiment_key' => 'homepage-hero-v1',
     *   'buckets' => array(
     *     array('key'=>'a','weight'=>50,'page_id'=>123,'popup_id'=>0),
     *     array('key'=>'b','weight'=>50,'page_id'=>456,'popup_id'=>0),
     *   ),
     *   'goal_event' => 'purchase'
     * )
     *
     * @param object|null $mapping Current resolved mapping.
     * @param object      $variant Variant group object.
     * @param string      $country Country code.
     * @param int         $master_page_id Master page ID.
     * @return object|null
     */
    public static function maybe_assign_bucket_target($mapping, $variant, $country, $master_page_id) {
        if (!$variant || !isset($variant->options) || !is_array($variant->options)) {
            return $mapping;
        }

        $ab = isset($variant->options['ab_test']) && is_array($variant->options['ab_test']) ? $variant->options['ab_test'] : array();
        if (empty($ab['enabled']) || empty($ab['buckets']) || !is_array($ab['buckets'])) {
            return $mapping;
        }

        $experiment_key = !empty($ab['experiment_key']) ? sanitize_key($ab['experiment_key']) : 'exp_' . intval($variant->id);
        $bucket = self::get_or_assign_bucket($variant, $experiment_key, $ab['buckets'], $master_page_id);
        if (!$bucket) {
            return $mapping;
        }

        $target = is_object($mapping) ? clone $mapping : (object) array(
            'page_id' => 0,
            'popup_id' => 0,
            'section_ref' => '',
            'widget_ref' => '',
        );

        if (!empty($bucket['page_id'])) {
            $target->page_id = intval($bucket['page_id']);
        }
        if (!empty($bucket['popup_id'])) {
            $target->popup_id = intval($bucket['popup_id']);
        }

        self::track_event(
            intval($variant->id),
            $experiment_key,
            sanitize_key($bucket['key']),
            'assignment',
            !empty($bucket['page_id']) ? 'page' : 'popup',
            !empty($bucket['page_id']) ? intval($bucket['page_id']) : intval($bucket['popup_id']),
            strtoupper((string) $country)
        );

        return $target;
    }

    /**
     * Persist and return experiment bucket.
     *
     * @param object $variant Variant.
     * @param string $experiment_key Experiment key.
     * @param array  $buckets Bucket definitions.
     * @param int    $master_page_id Master page ID.
     * @return array|null
     */
    private static function get_or_assign_bucket($variant, $experiment_key, $buckets, $master_page_id) {
        $variant_id = intval($variant->id);
        $store = self::get_assignment_store();
        $assignment_key = $variant_id . ':' . $experiment_key . ':' . intval($master_page_id);

        if (isset($store[$assignment_key])) {
            $assigned = sanitize_key($store[$assignment_key]);
            foreach ($buckets as $bucket) {
                if (isset($bucket['key']) && sanitize_key($bucket['key']) === $assigned) {
                    return $bucket;
                }
            }
        }

        $chosen = self::choose_weighted_bucket($buckets);
        if (!$chosen || empty($chosen['key'])) {
            return null;
        }

        $store[$assignment_key] = sanitize_key($chosen['key']);
        self::set_assignment_store($store);

        return $chosen;
    }

    /**
     * Weighted random bucket selection.
     *
     * @param array $buckets Buckets.
     * @return array|null
     */
    private static function choose_weighted_bucket($buckets) {
        $normalized = array();
        $sum = 0;
        foreach ($buckets as $bucket) {
            if (!is_array($bucket) || empty($bucket['key'])) {
                continue;
            }
            $weight = isset($bucket['weight']) ? max(0, intval($bucket['weight'])) : 0;
            if ($weight <= 0) {
                continue;
            }
            $sum += $weight;
            $normalized[] = array('sum' => $sum, 'bucket' => $bucket);
        }
        if ($sum <= 0 || empty($normalized)) {
            return null;
        }

        try {
            $pick = random_int(1, $sum);
        } catch (\Throwable $e) {
            $pick = mt_rand(1, $sum); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
        }

        foreach ($normalized as $item) {
            if ($pick <= $item['sum']) {
                return $item['bucket'];
            }
        }

        return $normalized[count($normalized) - 1]['bucket'];
    }

    /**
     * Parse assignment cookie into array.
     *
     * @return array
     */
    private static function get_assignment_store() {
        if (empty($_COOKIE[self::COOKIE_NAME])) {
            return array();
        }
        $decoded = json_decode(stripslashes((string) $_COOKIE[self::COOKIE_NAME]), true);
        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Persist assignment store in cookie.
     *
     * @param array $store Store.
     * @return void
     */
    private static function set_assignment_store($store) {
        $value = wp_json_encode($store);
        if (!$value) {
            return;
        }
        setcookie(self::COOKIE_NAME, $value, time() + (DAY_IN_SECONDS * 30), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        $_COOKIE[self::COOKIE_NAME] = $value;
    }

    /**
     * Track experiment event row.
     *
     * @return void
     */
    public static function track_event($variant_id, $experiment_key, $bucket_key, $event_type, $target_type, $target_id, $country_iso2) {
        global $wpdb;
        if (!$wpdb) {
            return;
        }

        $table = $wpdb->prefix . 'rw_geo_experiment_event';
        $visitor_hash = hash('sha256', (string) self::get_visitor_fingerprint());
        $wpdb->insert(
            $table,
            array(
                'variant_id' => intval($variant_id),
                'experiment_key' => sanitize_key($experiment_key),
                'bucket_key' => sanitize_key($bucket_key),
                'visitor_hash' => $visitor_hash,
                'event_type' => sanitize_key($event_type),
                'target_type' => sanitize_key($target_type),
                'target_id' => intval($target_id),
                'country_iso2' => strtoupper(substr(sanitize_text_field((string) $country_iso2), 0, 2)),
            )
        );
    }

    /**
     * Build lightweight visitor fingerprint.
     *
     * @return string
     */
    private static function get_visitor_fingerprint() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        return $ip . '|' . $ua;
    }
}

