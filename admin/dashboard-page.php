<?php
/**
 * Admin Dashboard Page for Geo Elementor
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class EGP_Admin_Dashboard {
	public function __construct() {
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
	}

	public function render_page() {
		echo '<div class="wrap"><h1>' . esc_html__('Geo Rules Dashboard', 'elementor-geo-popup') . "</h1>";
		if ( class_exists( 'EGP_Admin_Menu' ) ) {
			EGP_Admin_Menu::render_inner_nav( 'geo-elementor' );
		}
		echo '<div id="geo-el-admin-app"></div>';
		echo '</div>';
	}

	public function enqueue_assets($hook) {
		// Only load on our dashboard page
		if ($hook !== 'toplevel_page_geo-elementor') {
			return;
		}

		// Check if built dashboard files exist
		$dashboard_js = EGP_PLUGIN_URL . 'assets/js/dashboard/dashboard.js';
		$dashboard_css = EGP_PLUGIN_URL . 'assets/js/dashboard/dashboard.css';
		$dashboard_js_path = (defined('EGP_PLUGIN_DIR') ? EGP_PLUGIN_DIR : plugin_dir_path(dirname(__FILE__))) . 'assets/js/dashboard/dashboard.js';
		
		if (file_exists($dashboard_js_path)) {
			// Load lightweight vanilla JS dashboard
			$ver_js = function_exists('filemtime') ? @filemtime($dashboard_js_path) : EGP_VERSION;
			wp_enqueue_script('egp-dashboard', $dashboard_js, array(), $ver_js ?: EGP_VERSION, true);
			$css_path = (defined('EGP_PLUGIN_DIR') ? EGP_PLUGIN_DIR : plugin_dir_path(dirname(__FILE__))) . 'assets/js/dashboard/dashboard.css';
			$ver_css = function_exists('filemtime') ? @filemtime($css_path) : EGP_VERSION;
			wp_enqueue_style('egp-dashboard', $dashboard_css, array(), $ver_css ?: EGP_VERSION);

			// Provide REST base and nonce for authenticated wp-admin requests
			wp_localize_script('egp-dashboard', 'egpDashboard', array(
				'restBase' => rest_url('geo-elementor/v1/'),
				'nonce'   => wp_create_nonce('wp_rest'),
				'isAdmin' => is_admin(),
			));
		} else {
			// Fallback to inline loading message
			$inline_js = 'document.getElementById("geo-el-admin-app").innerHTML = "<div style=\\"text-align:center;padding:2rem;\\"><h3>Dashboard Loading...</h3><p>Please run <code>npm run build</code> to build the dashboard.</p></div>";';
			wp_add_inline_script('jquery', $inline_js, 'after');
		}
		
		// Add auto-refresh listener for when rules are saved from Elementor editor
		$refresh_script = "
		window.addEventListener('message', function(event) {
			if (event.data && event.data.type === 'egp_rule_saved') {
				console.log('[EGP Admin] Rule saved from editor, refreshing dashboard...', event.data);
				// Trigger dashboard reload if it has a reload method
				if (window.egpDashboard && typeof window.egpDashboard.reload === 'function') {
					window.egpDashboard.reload();
				} else {
					// Fallback: reload the entire page
					setTimeout(function() {
						location.reload();
					}, 500);
				}
			}
		});
		";
		wp_add_inline_script('jquery', $refresh_script, 'after');
	}
}


