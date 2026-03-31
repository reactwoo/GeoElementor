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
 * All country options for admin, Elementor controls, and REST (filterable).
 *
 * Filter: egp_country_options — receives array map of ISO2 => English label.
 *
 * @return array<string, string>
 */
function egp_get_country_options() {
    return apply_filters( 'egp_country_options', egp_get_country_options_raw() );
}
