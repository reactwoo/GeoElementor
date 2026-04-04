<?php
/**
 * Elementor editor / preview detection — bypass live geo rules while editing.
 *
 * @package ElementorGeoPopup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central helper: when true, Geo Elementor should not hide or replace geo-targeted content.
 */
class EGP_Editor_Context {

	/**
	 * Boot optional editor-only UX.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'maybe_render_preview_bypass_notice' ), 3 );
	}

	/**
	 * Whether geo visibility / replacement should be skipped (editor, preview iframe, Elementor AJAX for editors).
	 *
	 * @param int|null $post_id Optional document ID; resolved when null.
	 * @return bool
	 */
	public static function should_bypass_geo_rules( $post_id = null ) {
		if ( function_exists( 'rwgc_is_builder_edit_request' ) && rwgc_is_builder_edit_request( $post_id ) ) {
			$inner = true;
		} else {
			$inner = self::fallback_bypass_without_geo_core( $post_id );
		}

		$resolved = self::resolve_post_id_for_filter( $post_id );
		/**
		 * Whether Geo Elementor should treat the request as an editor/builder context (skip geo hiding).
		 *
		 * @param bool $inner    Default decision.
		 * @param int  $resolved Resolved document ID or 0.
		 */
		return (bool) apply_filters( 'egp_should_bypass_geo_rules', $inner, $resolved );
	}

	/**
	 * Minimal detection if Geo Core is unavailable (should not happen — Geo Elementor requires Geo Core).
	 *
	 * @param int|null $post_id Optional post ID.
	 * @return bool
	 */
	private static function fallback_bypass_without_geo_core( $post_id ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( ! empty( $_GET['elementor-preview'] ) || ! empty( $_GET['elementor_library'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return self::user_can_edit_resolved( $post_id );
		}

		if ( ! empty( $_GET['action'] ) && 'elementor' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return self::user_can_edit_resolved( $post_id );
		}

		if ( class_exists( '\Elementor\Plugin' ) ) {
			try {
				$plugin = \Elementor\Plugin::$instance;
				if ( $plugin && isset( $plugin->editor ) && is_object( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
					return self::user_can_edit_resolved( $post_id );
				}
				if ( $plugin && isset( $plugin->preview ) && is_object( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode() ) {
					return self::user_can_edit_resolved( $post_id );
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				return false;
			}
		}

		return false;
	}

	/**
	 * @param int|null $post_id Optional.
	 * @return bool
	 */
	private static function user_can_edit_resolved( $post_id ) {
		$rid = self::resolve_post_id_for_filter( $post_id );
		if ( $rid > 0 ) {
			return (bool) current_user_can( 'edit_post', $rid );
		}
		return (bool) ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) );
	}

	/**
	 * @param int|null $post_id Optional explicit ID.
	 * @return int
	 */
	private static function resolve_post_id_for_filter( $post_id ) {
		if ( null !== $post_id && (int) $post_id > 0 ) {
			return (int) $post_id;
		}
		if ( ! empty( $_GET['elementor-preview'] ) && is_numeric( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return (int) $_GET['elementor-preview'];
		}
		$q = (int) get_queried_object_id();
		return $q > 0 ? $q : 0;
	}

	/**
	 * Subtle notice on the Elementor preview iframe so editors know geo rules are not applied there.
	 *
	 * @return void
	 */
	public static function maybe_render_preview_bypass_notice() {
		if ( is_admin() || ! is_user_logged_in() ) {
			return;
		}
		if ( empty( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! self::should_bypass_geo_rules() ) {
			return;
		}
		echo '<div class="egp-geo-edit-bypass-notice" style="position:fixed;z-index:999999;left:12px;bottom:12px;max-width:min(420px,92vw);padding:10px 12px;margin:0;font-size:12px;line-height:1.45;color:#0f172a;background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;box-shadow:0 4px 12px rgba(15,23,42,0.12);font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif;">';
		echo esc_html__( 'Geo targeting is bypassed while editing in Elementor so you can see and modify this content. Live visitors still follow geo rules.', 'elementor-geo-popup' );
		echo '</div>';
	}
}

EGP_Editor_Context::init();
