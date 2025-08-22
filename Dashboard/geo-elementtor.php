<?php
/**
 * Plugin Name: Geo Elementor
 * Description: Geo targeting for Elementor: trigger popups, pages, sections, forms, and global elements by country/city. Includes an admin dashboard with mini-stats.
 * Version: 0.2.0
 * Author: ReactWoo
 * Requires at least: 6.3
 * Requires PHP: 8.0
 * Text Domain: geo-elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GEO_EL_PLUGIN_FILE', __FILE__ );
define( 'GEO_EL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GEO_EL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GEO_EL_VERSION', '0.2.0' );

final class Geo_Elementor_Plugin {
	private static $instance = null;

	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest' ] );
	}

	public function register_admin_menu(): void {
		add_menu_page(
			__( 'Geo Elementor', 'geo-elementor' ),
			__( 'Geo Elementor', 'geo-elementor' ),
			'manage_options',
			'geo-elementor',
			[ $this, 'render_dashboard_page' ],
			'dashicons-location',
			58
		);
	}

	public function render_dashboard_page(): void {
		?>
		<div class="wrap geo-el-wrap">
			<h1 style="margin-bottom:12px;"><?php esc_html_e( 'Geo Elementor Dashboard', 'geo-elementor' ); ?></h1>
			<p style="margin-top:0;color:#555;"><?php esc_html_e( 'Quick insights & controls for your geo rules. Create rules, toggle status, and filter targeted content.', 'geo-elementor' ); ?></p>
			<div id="geo-el-admin-app" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"></div>
		</div>
		<?php
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( $hook !== 'toplevel_page_geo-elementor' ) {
			return;
		}

		$asset_path = GEO_EL_PLUGIN_DIR . 'build/index.asset.php';
		$deps       = [ 'wp-element', 'wp-i18n', 'wp-api-fetch' ];
		$ver        = GEO_EL_VERSION;

		if ( file_exists( $asset_path ) ) {
			$asset = include $asset_path; // phpcs:ignore
			$deps  = array_unique( array_merge( $deps, $asset['dependencies'] ?? [] ) );
			$ver   = $asset['version'] ?? GEO_EL_VERSION;
		}

		wp_register_script(
			'geo-el-admin',
			GEO_EL_PLUGIN_URL . 'build/index.js',
			$deps,
			$ver,
			true
		);

		wp_register_style(
			'geo-el-admin',
			GEO_EL_PLUGIN_URL . 'build/index.css',
			[],
			$ver
		);

		wp_enqueue_script( 'geo-el-admin' );
		wp_enqueue_style( 'geo-el-admin' );

		$is_pro = (bool) apply_filters( 'geo_el_is_pro', false );

		wp_localize_script( 'geo-el-admin', 'GEO_EL',
			[
				'restRoot' => esc_url_raw( rest_url( 'geo-elementor/v1' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'version'  => GEO_EL_VERSION,
				'isPro'    => $is_pro,
			]
		);
	}

	public function register_rest(): void {
		require_once GEO_EL_PLUGIN_DIR . 'includes/class-geo-el-rest.php';
		Geo_El_Rest::register_routes();
	}
}

Geo_Elementor_Plugin::instance();
