<?php
/**
 * Canonical country list: ISO 3166-1 alpha-2 => English name from bundled assets/data/countries.json.
 *
 * @package ElementorGeoPopup
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Normalize a 2-letter country code key (e.g. UK → GB) to match MaxMind / Geo Core.
 *
 * @param string $code Raw code.
 * @return string
 */
function egp_normalize_country_code_key( $code ) {
    $code = strtoupper( trim( (string) $code ) );
    if ( strlen( $code ) !== 2 ) {
        return $code;
    }
    if ( class_exists( 'EGP_Geo_Detect' ) ) {
        return EGP_Geo_Detect::normalize_iso3166_alpha2( $code );
    }
    return ( 'UK' === $code ) ? 'GB' : $code;
}

/**
 * Visitor ISO2 for Elementor/geo targeting — prefers ReactWoo Geo Core when active.
 *
 * Delegates to {@see EGP_Geo_Detect::get_visitor_country()} when loaded (that method already
 * prefers rwgc_get_visitor_country when rwgc_is_ready). Falls back to Geo Core only if the
 * detect class is unavailable.
 *
 * @return string|false Two-letter code or false when unknown.
 */
function egp_get_visitor_country_for_targeting() {
    if ( class_exists( 'EGP_Geo_Detect' ) ) {
        return EGP_Geo_Detect::get_instance()->get_visitor_country();
    }
    if ( function_exists( 'rwgc_get_visitor_country' ) && function_exists( 'rwgc_is_ready' ) && rwgc_is_ready() ) {
        $country = strtoupper( (string) rwgc_get_visitor_country() );
        if ( $country && strlen( $country ) === 2 ) {
            return egp_normalize_country_code_key( $country );
        }
    }
    return false;
}

/**
 * Resolve Geo Core page routing “master” ID when the given page is a variant row.
 *
 * @param int $page_id Page ID from RWGC routing context.
 * @return int Effective master page ID for Pro variant maps.
 */
function egp_resolve_rwgc_master_page_id( $page_id ) {
    $page_id = absint( $page_id );
    if ( $page_id <= 0 || ! class_exists( 'RWGC_Routing', false ) ) {
        return $page_id;
    }
    $cfg = RWGC_Routing::get_page_route_config( $page_id );
    if ( ! empty( $cfg['enabled'] ) && isset( $cfg['role'] ) && 'variant' === $cfg['role'] && ! empty( $cfg['master_page_id'] ) ) {
        return absint( $cfg['master_page_id'] );
    }
    return $page_id;
}

/**
 * Absolute path to bundled countries.json.
 *
 * @return string
 */
function egp_get_countries_json_path() {
    if ( defined( 'EGP_PLUGIN_DIR' ) ) {
        return EGP_PLUGIN_DIR . 'assets/data/countries.json';
    }
    return dirname( __DIR__ ) . '/assets/data/countries.json';
}

/**
 * Minimal fallback if JSON is missing or unreadable.
 *
 * @return array<string, string>
 */
function egp_get_country_options_fallback() {
    return array(
        'US' => 'United States',
        'GB' => 'United Kingdom',
        'CA' => 'Canada',
        'AU' => 'Australia',
        'DE' => 'Germany',
        'FR' => 'France',
        'IT' => 'Italy',
        'ES' => 'Spain',
        'NL' => 'Netherlands',
        'BE' => 'Belgium',
    );
}

/**
 * Load countries from JSON (cached per request, not filtered).
 *
 * @return array<string, string>
 */
function egp_get_country_options_raw() {
    static $cached = null;
    if ( is_array( $cached ) ) {
        return $cached;
    }
    $path = egp_get_countries_json_path();
    $path = realpath( $path );
    $countries = array();
    if ( $path && is_readable( $path ) ) {
        $contents = file_get_contents( $path );
        if ( is_string( $contents ) && $contents !== '' ) {
            $decoded = json_decode( $contents, true );
            if ( is_array( $decoded ) ) {
                foreach ( $decoded as $row ) {
                    if ( ! is_array( $row ) || ! isset( $row['code'], $row['name'] ) ) {
                        continue;
                    }
                    $code = egp_normalize_country_code_key( $row['code'] );
                    $countries[ $code ] = $row['name'];
                }
            }
        }
    }
    if ( empty( $countries ) ) {
        $countries = egp_get_country_options_fallback();
    }
    $cached = $countries;
    return $cached;
}

/**
 * Prefer ReactWoo Geo Core’s country map when both plugins are active (§5.1 single canonical list).
 *
 * @param array<string, string> $countries ISO2 => label from {@see egp_get_country_options_raw()}.
 * @return array<string, string>
 */
function egp_merge_country_options_from_geo_core( $countries ) {
	if ( class_exists( 'RWGC_Countries', false ) ) {
		$rw = RWGC_Countries::get_options();
		if ( is_array( $rw ) && ! empty( $rw ) ) {
			return $rw;
		}
	}
	return is_array( $countries ) ? $countries : array();
}

add_filter( 'egp_country_options', 'egp_merge_country_options_from_geo_core', 5 );

/**
 * All country options for admin, Elementor controls, and REST (filterable).
 *
 * Filter: egp_country_options — receives array map of ISO2 => English label.
 *
 * @return array<string, string>
 */
function egp_get_country_options() {
	return apply_filters( 'egp_country_options', egp_get_country_options_raw() );
}
