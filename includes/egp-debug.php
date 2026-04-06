<?php
/**
 * Central debug toggles for Geo Elementor (avoid log spam on staging/production).
 *
 * @package ElementorGeoPopup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'egp_is_debug_mode' ) ) {
	/**
	 * Admin setting: verbose diagnostics (file log + inline JS console where applicable).
	 *
	 * @return bool
	 */
	function egp_is_debug_mode() {
		return (bool) get_option( 'egp_debug_mode' );
	}
}

if ( ! function_exists( 'egp_is_verbose_log_enabled' ) ) {
	/**
	 * Matches ElementorGeoPopup::should_log_info — info-level PHP logs only when both are on.
	 *
	 * @return bool
	 */
	function egp_is_verbose_log_enabled() {
		return ( defined( 'WP_DEBUG' ) && WP_DEBUG && egp_is_debug_mode() );
	}
}

if ( ! function_exists( 'egp_debug_log' ) ) {
	/**
	 * Write to PHP error_log only when {@see egp_is_debug_mode()} is true.
	 *
	 * @param string $message Message (no secrets).
	 * @return void
	 */
	function egp_debug_log( $message ) {
		if ( ! egp_is_debug_mode() || ! function_exists( 'error_log' ) ) {
			return;
		}
		error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated.
	}
}
