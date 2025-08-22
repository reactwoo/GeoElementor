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
		// Accept when loaded via our top-level menu
		if ($hook !== 'geo-elementor_page_geo-el-dashboard' && $hook !== 'settings_page_geo-el-dashboard') {
			return;
		}

		// WP element and api-fetch are available in admin. Ensure dependencies.
		wp_enqueue_script('wp-element');
		wp_enqueue_script('wp-api-fetch');

		// Inline minimal JS to mount a basic placeholder until we port React bundle.
		$inline_js = 'window.GEO_EL = window.GEO_EL || {};';
		$inline_js .= 'window.GEO_EL.nonce = ' . wp_json_encode(wp_create_nonce('wp_rest')) . ';';
		$inline_js .= 'window.GEO_EL.isPro = ' . (current_user_can('manage_woocommerce') ? 'true' : 'false') . ';';
		$inline_js .= '(function(){var m=document.getElementById("geo-el-admin-app"); if(!m) return; m.innerHTML = "<div class=\\"geo-el-admin\\">Loading…</div>";})();';
		wp_add_inline_script('wp-element', $inline_js, 'after');

		// Basic styles to avoid unstyled content; can be replaced by built bundle later
		$inline_css = '.geo-el-admin{font-family:system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;}';
		wp_register_style('egp-dashboard', false);
		wp_enqueue_style('egp-dashboard');
		wp_add_inline_style('egp-dashboard', $inline_css);
	}
}


