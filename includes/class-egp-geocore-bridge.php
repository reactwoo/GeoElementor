<?php
/**
 * Legacy bridge: Geo Elementor licence → advanced targeting (migration window only).
 *
 * @deprecated 2026-05 GeoElementor Pro is retired as a product; use GeoCore Pro ({@see RWGCP_Bootstrap}).
 * @package Elementor_Geo_Popup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @deprecated Prefer {@see rwgc_advanced_targeting_enabled()} via GeoCore Pro only.
 */
class EGP_Geocore_Bridge {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_advanced_targeting_enabled', array( __CLASS__, 'filter_advanced_targeting' ), 5, 1 );
	}

	/**
	 * Legacy geo-elementor licence holders until licence server maps slug to reactwoo-geocore-pro.
	 *
	 * @param bool $enabled Whether advanced targeting is already enabled.
	 * @return bool
	 */
	public static function filter_advanced_targeting( $enabled ) {
		if ( $enabled ) {
			return true;
		}
		return self::legacy_geo_elementor_licensed();
	}

	/**
	 * @return bool
	 */
	private static function legacy_geo_elementor_licensed() {
		if ( (bool) apply_filters( 'egp_is_pro_user', false ) ) {
			return true;
		}
		if ( ! class_exists( 'ElementorGeoPopup', false ) ) {
			return false;
		}
		$plugin = ElementorGeoPopup::get_instance();
		if ( $plugin && method_exists( $plugin, 'is_pro_licensed' ) ) {
			return (bool) $plugin->is_pro_licensed();
		}
		return false;
	}
}

if ( function_exists( 'rwgc_is_geo_core_active' ) && rwgc_is_geo_core_active() ) {
	EGP_Geocore_Bridge::init();
}
