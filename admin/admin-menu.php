<?php
/**
 * Admin Main Menu for Geo Elementor
 *
 * @package GeoElementor
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
		// Decide capability (filterable, defaults to manage_options; falls back to manage_woocommerce for shop managers)
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

		// Submenu: Settings (redirect to existing Options page)
		add_submenu_page(
			'geo-elementor',
			__('Settings', 'elementor-geo-popup'),
			__('Settings', 'elementor-geo-popup'),
			$capability,
			'geo-elementor-settings',
			array($this, 'redirect_settings')
		);

		// Submenu: License (redirect to existing License page)
		add_submenu_page(
			'geo-elementor',
			__('License', 'elementor-geo-popup'),
			__('License', 'elementor-geo-popup'),
			$capability,
			'geo-elementor-license',
			array($this, 'render_license_inline')
		);

		// Alternate slug in case WAF/security blocks 'license' in query strings
		add_submenu_page(
			'geo-elementor',
			__('License (Alt)', 'elementor-geo-popup'),
			__('License (Alt)', 'elementor-geo-popup'),
			$capability,
			'geo-keys',
			array($this, 'render_license_inline')
		);
	}

	public function render_dashboard() {
		// Redirect to the most appropriate page based on capability
		if (current_user_can('manage_options')) {
			$this->redirect_settings();
		} else {
			$this->redirect_license();
		}
	}

	public function redirect_settings() {
		wp_safe_redirect(admin_url('options-general.php?page=elementor-geo-popup'));
		exit;
	}

	public function render_license_inline() {
		// Render the license page directly to avoid WAF/redirection issues
		do_action('egp_render_license_page');
	}
}


