<?php
/**
 * Admin Main Menu for Geo Elementor (Top-level menu)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class EGP_Admin_Menu {
	private $menus_registered = false;

	public static function is_geo_elementor_admin_screen($screen = null) {
		if (!is_admin()) {
			return false;
		}

		if (!$screen && function_exists('get_current_screen')) {
			$screen = get_current_screen();
		}

		if ($screen && !empty($screen->id) && strpos((string) $screen->id, 'geo-elementor') !== false) {
			return true;
		}

		$page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
		return in_array($page, array(
			'geo-elementor',
			'elementor-geo-popup',
			'geo-content',
			'geo-elementor-rules',
			'geo-elementor-variants',
			'egp-addons',
			'geo-elementor-license',
		), true);
	}

	public static function get_sync_rules_url() {
		return wp_nonce_url(
			admin_url('admin-post.php?action=egp_sync_elementor_rules'),
			'egp_sync_elementor_rules'
		);
	}

	/**
	 * Delete/trash URL for a geo_rule post (valid ID + type + delete_post with post context).
	 *
	 * @param int $post_id Rule post ID.
	 * @return string Non-empty when the current user may trash this rule.
	 */
	public static function get_geo_rule_trash_link( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return '';
		}
		$post = get_post( $post_id );
		if ( ! $post || 'geo_rule' !== $post->post_type ) {
			return '';
		}
		$link = get_delete_post_link( $post_id );
		return is_string( $link ) ? $link : '';
	}

	public static function render_page_notices() {
		if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
			return;
		}

		if (!function_exists('rwgc_is_ready')) {
			echo '<div class="notice notice-warning">';
			echo '<p>';
			echo esc_html__('GeoElementor now uses ReactWoo Geo Core for country detection. Please install and activate the free ReactWoo Geo Core plugin to ensure accurate geolocation and shared settings across ReactWoo products.', 'elementor-geo-popup');
			echo '</p><p>';
			printf(
				/* translators: %s: ReactWoo Geo Core docs/download URL. */
				esc_html__('Download ReactWoo Geo Core: %s', 'elementor-geo-popup'),
				'https://reactwoo.com/reactwoo-geocore'
			);
			echo '</p></div>';
		}

		$license_status = get_option('egp_license_status', '');
		if ($license_status === 'invalid' || $license_status === 'expired') {
			$message = __('Your Geo Elementor license is invalid or expired. Please <a href="%s">activate your license</a> to continue using all features.', 'elementor-geo-popup');
			$license_page_url = admin_url('admin.php?page=geo-elementor-license');
			echo '<div class="notice notice-error is-dismissible"><p>' . wp_kses_post(sprintf($message, esc_url($license_page_url))) . '</p></div>';
		}
	}

	/**
	 * @param string $title             Page heading (may contain limited HTML).
	 * @param string $current           Inner nav slug.
	 * @param string $extra_notices_html Optional notices HTML (already escaped) appended after core plugin notices.
	 */
	public static function render_page_header( $title, $current = 'geo-elementor', $extra_notices_html = '' ) {
		// UX: identity (logo + H1 on one row) → section nav → full-width notices.
		// WordPress prints admin_notices before .wrap; see output_relocate_admin_notices_script().
		echo '<div class="egp-admin-header">';
		echo '<div class="egp-page-brand">';
		echo '<span class="egp-admin-logo-wrap">';
		echo '<img id="egp-admin-logo" src="' . esc_url( EGP_PLUGIN_URL . 'assets/img/GeoElementor.svg' ) . '" alt="" width="40" height="40" decoding="async" />';
		echo '</span>';
		echo '<div class="egp-page-title-wrap"><h1 class="egp-page-heading">' . wp_kses_post( $title ) . '</h1></div>';
		echo '</div>';
		self::render_inner_nav( $current );
		echo '<div class="egp-page-notices" role="region" aria-label="' . esc_attr__( 'Geo Elementor notices', 'elementor-geo-popup' ) . '">';
		self::render_page_notices();
		if ( is_string( $extra_notices_html ) && $extra_notices_html !== '' ) {
			echo $extra_notices_html;
		}
		echo '</div>';
		echo '</div>';
		self::output_relocate_admin_notices_script();
	}

	/**
	 * Geo Core suite quick links: Core + optional AI, Commerce, Optimise.
	 *
	 * @param string $variant `full` (dashboard) or `compact` (e.g. Rules — lighter card).
	 * @return void
	 */
	public static function render_geo_suite_quick_links( $variant = 'full' ) {
		if ( ! class_exists( 'RWGC_Admin_UI', false ) ) {
			return;
		}
		$variant = ( 'compact' === $variant ) ? 'compact' : 'full';
		$actions = array(
			array(
				'url'     => admin_url( 'admin.php?page=rwgc-dashboard' ),
				'label'   => __( 'Geo Core', 'elementor-geo-popup' ),
				'primary' => true,
			),
		);
		if ( RWGC_Admin_UI::is_plugin_active( 'reactwoo-geo-ai/reactwoo-geo-ai.php' ) ) {
			$actions[] = array(
				'url'   => admin_url( 'admin.php?page=rwga-dashboard' ),
				'label' => __( 'Geo AI', 'elementor-geo-popup' ),
			);
		}
		if ( RWGC_Admin_UI::is_plugin_active( 'reactwoo-geo-commerce/reactwoo-geo-commerce.php' ) ) {
			$actions[] = array(
				'url'   => admin_url( 'admin.php?page=rwgcm-dashboard' ),
				'label' => __( 'Geo Commerce', 'elementor-geo-popup' ),
			);
		}
		if ( RWGC_Admin_UI::is_plugin_active( 'reactwoo-geo-optimise/reactwoo-geo-optimise.php' ) ) {
			$actions[] = array(
				'url'   => admin_url( 'admin.php?page=rwgo-dashboard' ),
				'label' => __( 'Geo Optimise', 'elementor-geo-popup' ),
			);
		}
		if ( 'compact' === $variant ) {
			$wrap_classes = 'rwgc-card egp-geo-suite-links egp-geo-suite-links--compact';
			/* translators: subsection title above suite quick links */
			$title = __( 'Suite links', 'elementor-geo-popup' );
			$desc  = __( 'Other ReactWoo Geo plugins when installed.', 'elementor-geo-popup' );
		} else {
			$wrap_classes = 'rwgc-card rwgc-card--highlight egp-geo-suite-links';
			$title        = __( 'Geo suite', 'elementor-geo-popup' );
			$desc         = __( 'Jump to other ReactWoo Geo plugins. Geo Elementor extends Geo Core with Elementor rules and variant groups.', 'elementor-geo-popup' );
		}
		?>
		<div class="<?php echo esc_attr( $wrap_classes ); ?>" role="region" aria-label="<?php echo esc_attr__( 'ReactWoo Geo suite', 'elementor-geo-popup' ); ?>">
			<h2 class="egp-geo-suite-links__title"><?php echo esc_html( $title ); ?></h2>
			<p class="description"><?php echo esc_html( $desc ); ?></p>
			<?php RWGC_Admin_UI::render_quick_actions( $actions ); ?>
		</div>
		<?php
	}

	/**
	 * Enqueue Geo Core admin + suite CSS when available, then EGP bridge styles.
	 *
	 * @param string $hook Current admin hook (unused).
	 * @return void
	 */
	public function enqueue_geo_suite_assets( $hook = '' ) {
		if ( ! self::is_geo_elementor_admin_screen() ) {
			return;
		}
		if ( ! defined( 'RWGC_URL' ) || ! defined( 'RWGC_VERSION' ) ) {
			return;
		}
		wp_enqueue_style(
			'rwgc-admin',
			RWGC_URL . 'admin/css/admin.css',
			array(),
			RWGC_VERSION
		);
		wp_enqueue_style(
			'rwgc-suite',
			RWGC_URL . 'admin/css/rwgc-suite.css',
			array( 'rwgc-admin' ),
			RWGC_VERSION
		);
		wp_enqueue_style(
			'egp-geo-suite',
			EGP_PLUGIN_URL . 'admin/css/egp-geo-suite.css',
			array( 'rwgc-suite' ),
			EGP_VERSION
		);
	}

	public static function render_inner_nav($current = 'geo-elementor') {
		$items = array(
			'geo-elementor'          => __('Dashboard', 'elementor-geo-popup'),
			'elementor-geo-popup'    => __('Settings', 'elementor-geo-popup'),
			'geo-content'            => __('Geo Content', 'elementor-geo-popup'),
			'geo-elementor-rules'    => __('Rules', 'elementor-geo-popup'),
			'geo-elementor-variants' => __('Groups', 'elementor-geo-popup'),
			'egp-addons'             => __('Add-Ons', 'elementor-geo-popup'),
			'geo-elementor-license'  => __('License', 'elementor-geo-popup'),
		);
		echo '<nav class="egp-inner-nav" aria-label="' . esc_attr__('GeoElementor section navigation', 'elementor-geo-popup') . '">';
		foreach ($items as $slug => $label) {
			$class = 'egp-inner-nav__link' . ($slug === $current ? ' is-active' : '');
			echo '<a class="' . esc_attr($class) . '" href="' . esc_url(admin_url('admin.php?page=' . $slug)) . '">' . esc_html($label) . '</a>';
		}
		echo '</nav>';
	}

	public function __construct() {
		add_action('admin_menu', array($this, 'register_menus'), 9);
		add_action('admin_enqueue_scripts', array($this, 'enqueue_menu_icon_css'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_geo_suite_assets'), 15);
		add_action('admin_notices', array($this, 'maybe_show_core_notice'));
		if (did_action('admin_menu')) {
			$this->register_menus();
		}
		if (function_exists('egp_is_verbose_log_enabled') && egp_is_verbose_log_enabled()) {
			error_log('[EGP Menu] EGP_Admin_Menu constructed'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated.
		}
	}

	public function register_menus() {
		if ($this->menus_registered) {
			return;
		}
		$this->menus_registered = true;

		$default_cap = 'manage_options';
		if (!current_user_can('manage_options') && current_user_can('manage_woocommerce')) {
			$default_cap = 'manage_woocommerce';
		}
		$capability = apply_filters('egp_required_capability', $default_cap);
		if (!is_string($capability) || $capability === '') {
			$capability = $default_cap;
		}
		// Harden against third-party filters returning an unusable capability.
		if (!current_user_can($capability) && current_user_can('manage_options')) {
			$capability = 'manage_options';
		}

		// Top-level: Geo Elementor. Always register from this module.
		$icon_url = defined('EGP_PLUGIN_URL') ? EGP_PLUGIN_URL . 'assets/img/GeoElementor-icon.svg' : '';
		add_menu_page(
			__('Geo Elementor', 'elementor-geo-popup'),
			__('Geo Elementor', 'elementor-geo-popup'),
			$capability,
			'geo-elementor',
			array($this, 'render_dashboard'),
			$icon_url ?: 'dashicons-location-alt',
			58
		);

		// Submenu: Dashboard (renamed from Geo Elementor)
		add_submenu_page(
			'geo-elementor',
			__('Dashboard', 'elementor-geo-popup'),
			__('Dashboard', 'elementor-geo-popup'),
			$capability,
			'geo-elementor',
			array($this, 'render_dashboard')
		);

		// Submenu: Rules (renamed from Geo Rules)
		add_submenu_page(
			'geo-elementor',
			__('Rules', 'elementor-geo-popup'),
			__('Rules', 'elementor-geo-popup'),
			$capability,
			'geo-elementor-rules',
			array($this, 'render_rules')
		);

		// Variant Groups submenu is registered in RW_Geo_Variant_Groups_Admin

		// Submenu: Settings
		add_submenu_page(
			'geo-elementor',
			__('Settings', 'elementor-geo-popup'),
			__('Settings', 'elementor-geo-popup'),
			$capability,
			'elementor-geo-popup',
			array($this, 'render_settings')
		);

		// Submenu: Add-Ons
		add_submenu_page(
			'geo-elementor',
			__('Add-Ons', 'elementor-geo-popup'),
			__('Add-Ons', 'elementor-geo-popup'),
			$capability,
			'egp-addons',
			array($this, 'render_addons')
		);

		// Submenu: License
		add_submenu_page(
			'geo-elementor',
			__('License', 'elementor-geo-popup'),
			__('License', 'elementor-geo-popup'),
			$capability,
			'geo-elementor-license',
			array($this, 'render_license')
		);

		if (function_exists('egp_is_verbose_log_enabled') && egp_is_verbose_log_enabled()) {
			error_log('[EGP Menu] register_menus executed with capability: ' . $capability); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated.
		}

		do_action('egp_admin_menu_registered', $capability);
	}

	/**
	 * WP runs admin_notices in admin-header before the page callback. Move direct-child
	 * notice nodes into .egp-page-notices (inline after header; includes Elementor-style wrappers).
	 */
	public static function output_relocate_admin_notices_script() {
		if ( ! self::is_geo_elementor_admin_screen() ) {
			return;
		}
		?>
<script>
(function(){
	var wpbody = document.getElementById('wpbody-content');
	var target = document.querySelector('.wrap.egp-settings .egp-page-notices');
	if (!wpbody || !target) return;
	function isAdminNoticeNode(el){
		if (!el || !el.classList) return false;
		if (el.id === 'message') return true;
		if (el.classList.contains('notice') || el.classList.contains('updated') || el.classList.contains('error')) return true;
		if (el.classList.contains('e-admin-message') || el.classList.contains('elementor-message')) return true;
		return false;
	}
	var nodes = [];
	var i, el;
	for (i = 0; i < wpbody.children.length; i++) {
		el = wpbody.children[i];
		if (isAdminNoticeNode(el)) nodes.push(el);
	}
	for (i = nodes.length - 1; i >= 0; i--) {
		target.insertBefore(nodes[i], target.firstChild);
	}
})();
</script>
		<?php
	}

	/**
	 * Ensure custom admin menu icon matches core dimensions (20x20)
	 */
	public function enqueue_menu_icon_css($hook = '') {
		$css = '#toplevel_page_geo-elementor .wp-menu-image img{width:18px;height:18px;object-fit:contain;display:block;margin:7px auto;opacity:.6;transition:opacity .15s ease-in-out;padding:0;vertical-align:middle;}#toplevel_page_geo-elementor:hover .wp-menu-image img,#toplevel_page_geo-elementor.wp-has-current-submenu .wp-menu-image img,#toplevel_page_geo-elementor.current .wp-menu-image img{opacity:1;}';
		if ( self::is_geo_elementor_admin_screen() ) {
			$css .= '
.egp-admin-header{display:flex;flex-direction:column;align-items:stretch;width:100%;max-width:100%;margin:0 0 16px;box-sizing:border-box;}
.egp-page-brand{display:flex;flex-direction:row;flex-wrap:nowrap;align-items:flex-start;gap:12px;margin:0 0 12px;min-width:0;width:100%;}
.egp-page-brand .egp-admin-logo-wrap{flex-shrink:0;line-height:0;}
.egp-page-brand .egp-admin-logo-wrap img{display:block;height:40px;width:auto;max-width:100%;}
.egp-page-title-wrap{flex:1;min-width:0;}
.egp-page-title-wrap .egp-page-heading{margin:0;padding:0;line-height:1.25;font-size:23px;font-weight:400;letter-spacing:normal;}
.egp-inner-nav{margin:0 0 12px;flex:0 0 auto;width:100%;}
.egp-page-notices{clear:both;width:100%;max-width:100%;min-width:0;flex:0 0 auto;box-sizing:border-box;}
.egp-page-notices .notice{margin:0 0 10px;}
.egp-page-notices .notice:last-child{margin-bottom:0;}
@media (max-width:600px){.egp-page-brand{flex-wrap:wrap;}}
.egp-rule-actions{display:inline-flex;align-items:center;gap:4px;flex-wrap:nowrap;vertical-align:middle;}
.egp-rule-actions .button.egp-icon-btn{min-width:32px;padding:0 8px;line-height:1;display:inline-flex;align-items:center;justify-content:center;}
.egp-rule-actions .button.egp-icon-btn .dashicons{width:18px;height:18px;font-size:18px;}
.egp-rule-actions .button.egp-icon-btn .screen-reader-text{clip:rect(1px,1px,1px,1px);position:absolute!important;height:1px;width:1px;overflow:hidden;}
.wp-list-table .column-actions{width:88px;}
';
		}
		wp_register_style( 'egp-admin-menu-icon-fix', false );
		wp_enqueue_style( 'egp-admin-menu-icon-fix' );
		wp_add_inline_style( 'egp-admin-menu-icon-fix', $css );
	}

	public function render_dashboard() {
		echo '<div class="wrap egp-settings rwgc-wrap rwgc-suite">';
		self::render_page_header(esc_html__('Geo Rules Dashboard', 'elementor-geo-popup'), 'geo-elementor');
		self::render_geo_suite_quick_links();
		echo '<div class="notice notice-info" style="margin:14px 0;">';
		echo '<p>';
		echo esc_html__( 'Geo Core owns the free geo baseline and server-side page routing (Master + one Secondary per master). GeoElementor extends this with advanced variant groups and deeper element-level rules.', 'elementor-geo-popup' );
		echo '</p>';
		echo '<p>';
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=rwgc-usage' ) ) . '">' . esc_html__( 'Free Routing Guide', 'elementor-geo-popup' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( self::get_sync_rules_url() ) . '">' . esc_html__( 'Run Sync Now', 'elementor-geo-popup' ) . '</a> ';
		echo '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=geo-elementor-variants' ) ) . '">' . esc_html__( 'Manage Variant Groups', 'elementor-geo-popup' ) . '</a>';
		echo '</p>';
		echo '</div>';
		echo '<div id="geo-el-admin-app"></div>';
		echo '</div>';
	}

	/**
	 * Suggest ReactWoo Geo Core when not present so users understand the dependency.
	 */
	public function maybe_show_core_notice() {
		if ( self::is_geo_elementor_admin_screen() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'geo-elementor' ) === false ) {
			return;
		}
		if ( function_exists( 'rwgc_is_ready' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'GeoElementor now uses ReactWoo Geo Core for country detection. Please install and activate the free ReactWoo Geo Core plugin to ensure accurate geolocation and shared settings across ReactWoo products.', 'elementor-geo-popup' );
		echo '</p><p>';
		printf(
			/* translators: %s: ReactWoo Geo Core docs/download URL placeholder. */
			esc_html__( 'Download ReactWoo Geo Core: %s', 'elementor-geo-popup' ),
			'https://reactwoo.com/reactwoo-geocore'
		);
		echo '</p></div>';
	}

	public function render_rules() {
		echo '<div class="wrap egp-settings rwgc-wrap rwgc-suite">';
		self::render_page_header(
			esc_html__('Geo Rules', 'elementor-geo-popup') . ' <span class="dashicons dashicons-editor-help" title="Rules target a specific Page or Popup with selected countries. If an element is managed by a Group, avoid creating a duplicate Rule for the same element to prevent conflicts."></span>',
			'geo-elementor-rules'
		);
		self::render_geo_suite_quick_links( 'compact' );
		echo '<div class="notice notice-info" style="margin:14px 0;">';
		echo '<p>';
		echo esc_html__( 'Geo Core (free) handles shared geo engine + page-level server-side routing (Master + one Secondary per master).', 'elementor-geo-popup' ) . '<br />';
		echo esc_html__( 'GeoElementor Pro uses Rules/Groups for advanced element/page/popup targeting.', 'elementor-geo-popup' );
		echo '</p>';
		echo '<p>';
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=rwgc-usage' ) ) . '">' . esc_html__( 'Free Routing Guide', 'elementor-geo-popup' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( self::get_sync_rules_url() ) . '">' . esc_html__( 'Run Sync Now', 'elementor-geo-popup' ) . '</a> ';
		echo '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=geo-elementor-variants' ) ) . '">' . esc_html__( 'Variant Groups', 'elementor-geo-popup' ) . '</a>';
		echo '</p>';
		echo '</div>';
		
		// Add custom CSS for status indicators
		echo '<style>
			.status-active { color: #46b450; font-weight: bold; }
			.status-inactive { color: #dc3232; font-weight: bold; }
			.source-elementor { color: #556068; font-weight: bold; }
			.source-manual { color: #0073aa; font-weight: bold; }
			.egp-elementor-badge { 
				background: #556068; 
				color: white; 
				padding: 2px 6px; 
				border-radius: 3px; 
				font-size: 10px; 
				margin-left: 8px; 
			}
			.wp-list-table .column-title { width: 20%; }
			.wp-list-table .column-source { width: 8%; }
			.wp-list-table .column-type { width: 10%; }
			.wp-list-table .column-target { width: 18%; }
			.wp-list-table .column-countries { width: 15%; }
			.wp-list-table .column-status { width: 8%; }
			.wp-list-table .column-created { width: 12%; }
			.wp-list-table .column-clicks { width: 8%; }
			.egp-row-actions-icons a.egp-row-icon { text-decoration: none; }
			.egp-row-actions-icons a.egp-row-icon .dashicons { font-size: 16px; width: 16px; height: 16px; vertical-align: text-bottom; }
		</style>';
		
		// Get all geo rules
		$rules = get_posts(array(
			'post_type' => 'geo_rule',
			'post_status' => 'any',
			'numberposts' => -1,
			'orderby' => 'date',
			'order' => 'DESC'
		));
		
		if (empty($rules)) {
			echo '<div class="notice notice-info"><p>' . esc_html__('No geo rules found. Create your first rule to get started.', 'elementor-geo-popup') . '</p></div>';
			echo '<p><a href="' . esc_url(admin_url('post-new.php?post_type=geo_rule')) . '" class="button button-primary">' . esc_html__('Add New Rule', 'elementor-geo-popup') . '</a></p>';
			echo '</div>';
			return;
		}
		
		echo '<div class="tablenav top">';
		echo '<div class="alignleft actions">';
		echo '<a href="' . esc_url(admin_url('post-new.php?post_type=geo_rule')) . '" class="button button-primary">' . esc_html__('Add New Rule', 'elementor-geo-popup') . '</a>';
		echo '</div>';
		echo '</div>';
		
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead>';
		echo '<tr>';
		echo '<th scope="col" class="manage-column column-title column-primary">' . esc_html__('Rule Name', 'elementor-geo-popup') . '</th>';
		echo '<th scope="col" class="manage-column">' . esc_html__('Source', 'elementor-geo-popup') . '</th>';
		echo '<th scope="col" class="manage-column">' . esc_html__('Type', 'elementor-geo-popup') . '</th>';
		echo '<th scope="col" class="manage-column">' . esc_html__('Target', 'elementor-geo-popup') . '</th>';
		echo '<th scope="col" class="manage-column">' . esc_html__('Countries', 'elementor-geo-popup') . '</th>';
		echo '<th scope="col" class="manage-column">' . esc_html__('Status', 'elementor-geo-popup') . '</th>';
		echo '<th scope="col" class="manage-column">' . esc_html__('Created', 'elementor-geo-popup') . '</th>';
		echo '<th scope="col" class="manage-column">' . esc_html__('Clicks', 'elementor-geo-popup') . '</th>';
		echo '<th scope="col" class="manage-column">' . esc_html__('Actions', 'elementor-geo-popup') . '</th>';
		echo '</tr>';
		echo '</thead>';
		
		echo '<tbody>';
		foreach ($rules as $rule) {
			$target_type = get_post_meta($rule->ID, 'egp_target_type', true);
			$target_id = get_post_meta($rule->ID, 'egp_target_id', true);
			$countries = get_post_meta($rule->ID, 'egp_countries', true);
			$is_active = get_post_meta($rule->ID, 'egp_active', true);
			$clicks = get_post_meta($rule->ID, 'egp_clicks', true) ?: 0;
			$source = get_post_meta($rule->ID, 'egp_source', true) ?: 'manual';
			$element_type = get_post_meta($rule->ID, 'egp_element_type', true);
			
			// Format target display
			$target_display = '';
			if ($target_type === 'popup') {
				$target_display = 'Popup: ' . ($target_id ?: 'All Popups');
			} elseif ($target_type === 'page') {
				$target_display = 'Page: ' . ($target_id ?: 'All Pages');
			} elseif ($target_type === 'widget') {
				$target_display = 'Widget: ' . ($target_id ?: 'All Widgets');
			} elseif ($target_type === 'elementor') {
				$target_display = 'Elementor: ' . ucfirst($element_type ?: 'Element') . ' #' . $target_id;
			} else {
				$target_display = ucfirst($target_type ?: 'Unknown');
			}
			
			// Format countries
			$countries_display = '';
			if (is_array($countries) && !empty($countries)) {
				// Get country names from codes
				$country_names = array();
				foreach (array_slice($countries, 0, 3) as $code) {
					$code = strtoupper(trim($code));
					// Try to get full country name, fallback to code
					$country_names[] = $this->get_country_name($code) ?: $code;
				}
				$countries_display = implode(', ', $country_names);
				if (count($countries) > 3) {
					$countries_display .= ' (+' . (count($countries) - 3) . ' more)';
				}
			} else {
				$countries_display = 'All Countries';
			}
			
			// Status indicator
			$status_class = $is_active ? 'status-active' : 'status-inactive';
			$status_text = $is_active ? __('Active', 'elementor-geo-popup') : __('Inactive', 'elementor-geo-popup');
			
			// Source indicator
			$source_text = $source === 'elementor' ? 'Elementor' : 'Manual';
			$source_class = $source === 'elementor' ? 'source-elementor' : 'source-manual';
			
			echo '<tr>';
			echo '<td class="title column-title has-row-actions column-primary">';
			echo '<strong>' . esc_html($rule->post_title) . '</strong>';
			if ($source === 'elementor') {
				echo ' <span class="egp-elementor-badge">Elementor</span>';
			}
			echo '<div class="row-actions egp-row-actions-icons">';
			if ($source === 'elementor') {
				echo '<span class="edit"><a href="#" class="egp-row-icon" onclick="egpEditElementorRule(' . intval( $rule->ID ) . ');return false;" title="' . esc_attr__( 'Edit in Elementor', 'elementor-geo-popup' ) . '"><span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Edit in Elementor', 'elementor-geo-popup' ) . '</span></a> | </span>';
			} else {
				echo '<span class="edit"><a href="' . esc_url( get_edit_post_link( $rule->ID ) ) . '" class="egp-row-icon" title="' . esc_attr__( 'Edit', 'elementor-geo-popup' ) . '"><span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Edit', 'elementor-geo-popup' ) . '</span></a> | </span>';
			}
			$trash_link = self::get_geo_rule_trash_link( $rule->ID );
			if ( $trash_link !== '' ) {
				echo '<span class="trash"><a href="' . esc_url( $trash_link ) . '" class="egp-row-icon" title="' . esc_attr__( 'Delete', 'elementor-geo-popup' ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to delete this rule?', 'elementor-geo-popup' ) ) . '\')"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Delete', 'elementor-geo-popup' ) . '</span></a></span>';
			}
			echo '</div>';
			echo '</td>';
			echo '<td><span class="' . esc_attr($source_class) . '">' . esc_html($source_text) . '</span></td>';
			echo '<td>' . esc_html(ucfirst($target_type ?: 'Unknown')) . '</td>';
			echo '<td>' . esc_html($target_display) . '</td>';
			echo '<td>' . esc_html($countries_display) . '</td>';
			echo '<td><span class="' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span></td>';
			echo '<td>' . esc_html(date('M j, Y', strtotime($rule->post_date))) . '</td>';
			echo '<td>' . esc_html($clicks) . '</td>';
			echo '<td class="egp-rule-actions-cell">';
			echo '<span class="egp-rule-actions" role="group" aria-label="' . esc_attr__( 'Rule actions', 'elementor-geo-popup' ) . '">';
			if ($source === 'elementor') {
				$target_type_val = get_post_meta($rule->ID, 'egp_target_type', true);
				$target_id_val = get_post_meta($rule->ID, 'egp_target_id', true);
				$edit_url = '';
				if ($target_type_val === 'popup' && !empty($target_id_val)) {
					$popup_id_val = intval($target_id_val);
					$tpl_val = get_post_meta($popup_id_val, '_elementor_template_type', true);
					if ($tpl_val === 'popup') {
						$edit_url = admin_url('post.php?post=' . $popup_id_val . '&action=elementor');
					}
				}
				if ($edit_url) {
					echo '<a href="' . esc_url($edit_url) . '" target="_blank" rel="noopener noreferrer" class="button button-small egp-icon-btn" title="' . esc_attr__( 'Edit in Elementor', 'elementor-geo-popup' ) . '"><span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Edit in Elementor', 'elementor-geo-popup' ) . '</span></a>';
				} else {
					echo '<span class="button button-small egp-icon-btn" title="' . esc_attr__( 'Open this popup in Elementor from Templates → Popups.', 'elementor-geo-popup' ) . '" style="opacity:.45;cursor:not-allowed;pointer-events:none;" aria-disabled="true"><span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Edit in Elementor (unavailable)', 'elementor-geo-popup' ) . '</span></span>';
				}
			} else {
				echo '<a href="' . esc_url( get_edit_post_link( $rule->ID ) ) . '" class="button button-small egp-icon-btn" title="' . esc_attr__( 'Edit', 'elementor-geo-popup' ) . '"><span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Edit', 'elementor-geo-popup' ) . '</span></a>';
			}
			$trash_link_btn = self::get_geo_rule_trash_link( $rule->ID );
			if ( $trash_link_btn !== '' ) {
				echo '<a href="' . esc_url( $trash_link_btn ) . '" class="button button-small button-link-delete egp-icon-btn" title="' . esc_attr__( 'Delete', 'elementor-geo-popup' ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to delete this rule?', 'elementor-geo-popup' ) ) . '\')"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Delete', 'elementor-geo-popup' ) . '</span></a>';
			}
			echo '</span>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody>';
		echo '</table>';
		
		// Add JavaScript for Elementor rule editing: open Elementor editor for the target popup
		echo '<script>
		function egpEditElementorRule(ruleId) {
			try {
				var row = document.querySelector("tr[id^=\'post-\']");
			} catch(e) {}
			// Request the target popup ID via AJAX to build proper Elementor edit URL
			var xhr = new XMLHttpRequest();
			xhr.open("POST", "' . admin_url('admin-ajax.php') . '", true);
			xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
			xhr.onreadystatechange = function(){
				if (xhr.readyState === 4) {
					try {
						var res = JSON.parse(xhr.responseText || "{}");
						if (res && res.success && res.data && res.data.target_id) {
							var pid = parseInt(res.data.target_id, 10);
							if (pid > 0) {
								var url = "' . admin_url('post.php') . '?post=" + pid + "&action=elementor";
								window.open(url, "_blank");
								return;
							}
						}
					} catch(e) {}
					alert("Elementor editor not available for this popup. Please open the popup directly in Elementor.");
				}
			};
			xhr.send("action=egp_get_rule_target&nonce=' . wp_create_nonce('egp_admin_nonce') . '&rule_id=" + encodeURIComponent(ruleId));
		}
		</script>';
		
		echo '</div>';
	}

	/**
	 * Get country name from ISO code
	 */
	private function get_country_name($code) {
		if ( function_exists( 'egp_get_country_options' ) ) {
			$map = egp_get_country_options();
			return isset( $map[ $code ] ) ? $map[ $code ] : null;
		}
		return null;
	}

	public function render_variants() {
		// Render Variant Groups page via class if available
		if (class_exists('RW_Geo_Variant_Groups_Admin')) {
			$variants = RW_Geo_Variant_Groups_Admin::get_instance();
			$variants->render_admin_page();
			return;
		}
		
		// Fallback if class not available
		echo '<div class="wrap"><h1>' . esc_html__('Variant Groups', 'elementor-geo-popup') . '</h1>';
		echo '<div class="notice notice-error"><p>' . esc_html__('Variant Groups functionality not available. Please ensure the plugin is properly loaded.', 'elementor-geo-popup') . '</p></div>';
		echo '</div>';
	}

	public function render_settings() {
		// Render Settings page via class if available
		if (class_exists('EGP_Admin_Settings')) {
			// Find global instance if stored, otherwise instantiate a temp one to render
			$settings = new EGP_Admin_Settings();
			$settings->render_settings_page();
			return;
		}
		do_action('egp_render_settings_page');
	}

	public function render_addons() {
		// Render Add-Ons page via add-on manager
		if (class_exists('EGP_Addon_Manager')) {
			$addon_manager = EGP_Addon_Manager::get_instance();
			$addon_manager->admin_page();
			return;
		}
		
		// Fallback if add-on manager not available
		echo '<div class="wrap"><h1>' . esc_html__('Add-Ons', 'elementor-geo-popup') . '</h1>';
		echo '<div class="notice notice-error"><p>' . esc_html__('Add-On manager not available. Please ensure the plugin is properly loaded.', 'elementor-geo-popup') . '</p></div>';
		echo '</div>';
	}

	public function render_license() {
		do_action('egp_render_license_page');
	}
}


