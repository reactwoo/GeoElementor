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
			wp_enqueue_script('egp-dashboard', $dashboard_js, array(), EGP_VERSION, true);
			wp_enqueue_style('egp-dashboard', $dashboard_css, array(), EGP_VERSION);
		} else {
			// Fallback to inline loading message
			$inline_js = 'document.getElementById("geo-el-admin-app").innerHTML = "<div style=\\"text-align:center;padding:2rem;\\"><h3>Dashboard Loading...</h3><p>Please run <code>npm run build</code> to build the dashboard.</p></div>";';
			wp_add_inline_script('jquery', $inline_js, 'after');
		}
	}
}


