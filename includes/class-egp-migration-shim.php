<?php
/**
 * Geo Elementor migration shim — functionality moved to GeoCore / GeoCore Pro.
 *
 * @package ElementorGeoPopup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin notices and duplicate-hook prevention while Geo Elementor is retired.
 */
class EGP_Migration_Shim {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		add_filter( 'egp_is_pro_user', array( __CLASS__, 'filter_legacy_pro' ), 100, 1 );
	}

	/**
	 * @return void
	 */
	public static function admin_notice() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! class_exists( 'RWGC_Plugin', false ) ) {
			return;
		}

		$dismissed = get_user_meta( get_current_user_id(), 'egp_migration_notice_dismissed', true );
		if ( $dismissed ) {
			return;
		}

		$geocore_url = admin_url( 'admin.php?page=rwgc-dashboard' );
		$pro_url     = class_exists( 'RWGCP_Admin', false )
			? admin_url( 'admin.php?page=rwgcp-geocore-pro&rwgcp_tab=setup' )
			: $geocore_url;

		echo '<div class="notice notice-info is-dismissible" data-egp-migration-notice="1"><p>';
		echo esc_html__( 'Geo Elementor is now a compatibility layer. Elementor and Gutenberg geo targeting live in ReactWoo Geo Core (free country targeting) and GeoCore Pro (advanced rules).', 'elementor-geo-popup' );
		echo ' ';
		printf(
			'<a href="%1$s">%2$s</a> · <a href="%3$s">%4$s</a>',
			esc_url( $geocore_url ),
			esc_html__( 'Open Geo Core', 'elementor-geo-popup' ),
			esc_url( $pro_url ),
			esc_html__( 'GeoCore Pro setup', 'elementor-geo-popup' )
		);
		echo '</p></div>';
	}

	/**
	 * Legacy Pro checks should defer to GeoCore Pro when available.
	 *
	 * @param bool $is_pro Current value.
	 * @return bool
	 */
	public static function filter_legacy_pro( $is_pro ) {
		if ( function_exists( 'rwgc_advanced_targeting_enabled' ) && rwgc_advanced_targeting_enabled() ) {
			return true;
		}
		return (bool) $is_pro;
	}
}

EGP_Migration_Shim::init();
