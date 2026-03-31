<?php
/**
 * Geo Elementor — updates via ReactWoo API (same contract as ReactWoo WHMCS Bridge).
 *
 * Server: POST {API_BASE}/api/v5/updates/check
 * Body:   { slug, current_version, channel, site_host }
 * Expect: { update: true, version, download_url, tested_up_to?, min_wp?, min_php?, changelog_html? }
 *
 * @package ElementorGeoPopup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default API base (api.reactwoo.com). Override in wp-config: define( 'EGP_UPDATES_API_BASE_URL', 'https://...' );
 *
 * @return string
 */
function egp_updates_default_api_base() {
	static $cached = null;
	if ( null !== $cached ) {
		return $cached;
	}
	$cached = base64_decode( 'aHR0cHM6Ly9hcGkucmVhY3R3b28uY29t', true );
	$cached = is_string( $cached ) && $cached !== '' ? rtrim( $cached, '/' ) : '';
	return $cached;
}

if ( ! defined( 'EGP_UPDATES_API_BASE_URL' ) ) {
	define( 'EGP_UPDATES_API_BASE_URL', egp_updates_default_api_base() );
}

/**
 * Product slug registered on the ReactWoo update/license pipeline (must match server catalog).
 */
if ( ! defined( 'EGP_UPDATES_SLUG' ) ) {
	define( 'EGP_UPDATES_SLUG', 'geo-elementor' );
}

/**
 * Headers for POST /api/v5/updates/check (includes license Bearer when the site has activated).
 *
 * @return array<string, string>
 */
function egp_updates_request_headers() {
	$headers = array(
		'Content-Type' => 'application/json',
	);
	$token = get_option( 'egp_license_access_token', '' );
	if ( is_string( $token ) && $token !== '' ) {
		$headers['Authorization'] = 'Bearer ' . $token;
	}
	return apply_filters( 'egp_updates_request_headers', $headers );
}

add_filter( 'pre_set_site_transient_update_plugins', 'egp_check_for_updates' );
add_filter( 'plugins_api', 'egp_plugin_information', 10, 3 );

/**
 * @param stdClass $transient Update transient.
 * @return stdClass
 */
function egp_check_for_updates( $transient ) {
	if ( ! is_object( $transient ) ) {
		return $transient;
	}

	if ( empty( $transient->checked ) || ! isset( $transient->checked[ EGP_PLUGIN_BASENAME ] ) ) {
		return $transient;
	}

	$current_version = EGP_VERSION;
	$slug            = EGP_UPDATES_SLUG;
	$site_host       = wp_parse_url( home_url(), PHP_URL_HOST );

	$body = array(
		'slug'            => $slug,
		'current_version' => $current_version,
		'channel'         => 'stable',
		'site_host'       => $site_host ? $site_host : '',
	);

	$api_base = EGP_UPDATES_API_BASE_URL;
	if ( $api_base === '' ) {
		return $transient;
	}

	$response = wp_remote_post(
		trailingslashit( $api_base ) . 'api/v5/updates/check',
		array(
			'timeout' => 10,
			'headers' => egp_updates_request_headers(),
			'body'    => wp_json_encode( $body ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $transient;
	}

	if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return $transient;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) || empty( $data['update'] ) || empty( $data['version'] ) || empty( $data['download_url'] ) ) {
		return $transient;
	}

	if ( version_compare( $current_version, $data['version'], '>=' ) ) {
		return $transient;
	}

	$plugin_info              = new stdClass();
	$plugin_info->slug        = $slug;
	$plugin_info->plugin      = EGP_PLUGIN_BASENAME;
	$plugin_info->new_version = $data['version'];
	$plugin_info->package     = $data['download_url'];

	if ( ! empty( $data['tested_up_to'] ) ) {
		$plugin_info->tested = $data['tested_up_to'];
	}
	if ( ! empty( $data['min_wp'] ) ) {
		$plugin_info->requires = $data['min_wp'];
	}

	$transient->response[ EGP_PLUGIN_BASENAME ] = $plugin_info;

	return $transient;
}

/**
 * @param false|object|array $result
 * @param string               $action
 * @param object               $args
 * @return false|object|array
 */
function egp_plugin_information( $result, $action, $args ) {
	if ( 'plugin_information' !== $action ) {
		return $result;
	}

	if ( empty( $args->slug ) || EGP_UPDATES_SLUG !== $args->slug ) {
		return $result;
	}

	$slug      = EGP_UPDATES_SLUG;
	$site_host = wp_parse_url( home_url(), PHP_URL_HOST );

	$body = array(
		'slug'            => $slug,
		'current_version' => '0.0.0',
		'channel'         => 'stable',
		'site_host'       => $site_host ? $site_host : '',
	);

	$api_base = EGP_UPDATES_API_BASE_URL;
	if ( $api_base === '' ) {
		return $result;
	}

	$response = wp_remote_post(
		trailingslashit( $api_base ) . 'api/v5/updates/check',
		array(
			'timeout' => 10,
			'headers' => egp_updates_request_headers(),
			'body'    => wp_json_encode( $body ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $result;
	}

	if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return $result;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) || empty( $data['version'] ) ) {
		return $result;
	}

	$info               = new stdClass();
	$info->name         = 'Geo Elementor';
	$info->slug         = $slug;
	$info->version      = $data['version'];
	$info->requires     = ! empty( $data['min_wp'] ) ? $data['min_wp'] : '';
	$info->tested       = ! empty( $data['tested_up_to'] ) ? $data['tested_up_to'] : '';
	$info->requires_php = ! empty( $data['min_php'] ) ? $data['min_php'] : '';
	$info->author       = '<a href="https://reactwoo.com">ReactWoo</a>';
	$info->homepage     = 'https://reactwoo.com';

	$icon_file = 'assets/img/GeoElementor.svg';
	$icon_url  = file_exists( EGP_PLUGIN_DIR . $icon_file )
		? trailingslashit( EGP_PLUGIN_URL ) . $icon_file
		: '';
	if ( $icon_url !== '' ) {
		$info->icons = array(
			'1x' => $icon_url,
			'2x' => $icon_url,
		);
	}

	$info->sections = array(
		'description' => __( 'Advanced geo-targeting for Elementor with rules, variant groups, and Geo Core integration.', 'elementor-geo-popup' ),
		'changelog'     => ! empty( $data['changelog_html'] ) ? $data['changelog_html'] : '',
	);

	return $info;
}
