<?php
/**
 * Admin Main Menu for Geo Elementor (Top-level menu)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class EGP_Admin_Menu {
	public function __construct() {
		add_action('admin_menu', array($this, 'register_menus'), 9);
	}

	public function register_menus() {
		$default_cap = 'manage_options';
		if (!current_user_can('manage_options') && current_user_can('manage_woocommerce')) {
			$default_cap = 'manage_woocommerce';
		}
		$capability = apply_filters('egp_required_capability', $default_cap);

		// Top-level: Geo Elementor
		add_menu_page(
			__('Geo Elementor', 'elementor-geo-popup'),
			__('Geo Elementor', 'elementor-geo-popup'),
			$capability,
			'geo-elementor',
			array($this, 'render_dashboard'),
			'dashicons-location-alt',
			58
		);

		// Submenu: Dashboard
		add_submenu_page(
			'geo-elementor',
			__('Dashboard', 'elementor-geo-popup'),
			__('Dashboard', 'elementor-geo-popup'),
			$capability,
			'geo-el-dashboard',
			array($this, 'render_dashboard')
		);

		// Submenu: Settings
		add_submenu_page(
			'geo-elementor',
			__('Settings', 'elementor-geo-popup'),
			__('Settings', 'elementor-geo-popup'),
			$capability,
			'elementor-geo-popup',
			array($this, 'render_settings')
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

		// Geo Rules CPT will appear under this top-level via show_in_menu.
	}

	public function render_dashboard() {
		echo '<div class="wrap"><h1>' . esc_html__('Geo Rules Dashboard', 'elementor-geo-popup') . '</h1>';
		echo '<div id="geo-el-admin-app"></div></div>';
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

	public function render_license() {
		do_action('egp_render_license_page');
	}
}


