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
		if (!current_user_can('manage_options')) {
			return;
		}

		// Top-level: Geo Elementor
		add_menu_page(
			__('Geo Elementor', 'elementor-geo-popup'),
			__('Geo Elementor', 'elementor-geo-popup'),
			'manage_options',
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
			'manage_options',
			'geo-elementor-settings',
			array($this, 'redirect_settings')
		);

		// Submenu: License (redirect to existing License page)
		add_submenu_page(
			'geo-elementor',
			__('License', 'elementor-geo-popup'),
			__('License', 'elementor-geo-popup'),
			'manage_options',
			'geo-elementor-license',
			array($this, 'redirect_license')
		);
	}

	public function render_dashboard() {
		// Keep it simple: redirect Dashboard to Settings for now
		$this->redirect_settings();
	}

	public function redirect_settings() {
		wp_safe_redirect(admin_url('options-general.php?page=elementor-geo-popup'));
		exit;
	}

	public function redirect_license() {
		wp_safe_redirect(admin_url('options-general.php?page=egp-license'));
		exit;
	}
}


