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

		// Top-level: Geo Elementor (no callback - just container)
		add_menu_page(
			__('Geo Elementor', 'elementor-geo-popup'),
			__('Geo Elementor', 'elementor-geo-popup'),
			$capability,
			'geo-elementor',
			'',
			'dashicons-location-alt',
			58
		);

		// Submenu: Dashboard (first priority)
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

		// Submenu: Variant Groups
		add_submenu_page(
			'geo-elementor',
			__('Variant Groups', 'elementor-geo-popup'),
			__('Variant Groups', 'elementor-geo-popup'),
			$capability,
			'geo-elementor-variants',
			array($this, 'render_variants')
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
	}

	public function render_dashboard() {
		echo '<div class="wrap"><h1>' . esc_html__('Geo Rules Dashboard', 'elementor-geo-popup') . '</h1>';
		echo '<div id="geo-el-admin-app"></div></div>';
	}

	public function render_rules() {
		echo '<div class="wrap"><h1>' . esc_html__('Geo Rules', 'elementor-geo-popup') . '</h1>';
		
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
			.wp-list-table .column-actions { width: 12%; }
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
				$countries_display = implode(', ', array_slice($countries, 0, 3));
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
			echo '<div class="row-actions">';
			if ($source === 'elementor') {
				echo '<span class="edit"><a href="#" onclick="egpEditElementorRule(' . $rule->ID . ')">' . esc_html__('Edit in Elementor', 'elementor-geo-popup') . '</a> | </span>';
			} else {
				echo '<span class="edit"><a href="' . esc_url(get_edit_post_link($rule->ID)) . '">' . esc_html__('Edit', 'elementor-geo-popup') . '</a> | </span>';
			}
			echo '<span class="trash"><a href="' . esc_url(get_delete_post_link($rule->ID)) . '" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this rule?', 'elementor-geo-popup')) . '\')">' . esc_html__('Delete', 'elementor-geo-popup') . '</a></span>';
			echo '</div>';
			echo '</td>';
			echo '<td><span class="' . esc_attr($source_class) . '">' . esc_html($source_text) . '</span></td>';
			echo '<td>' . esc_html(ucfirst($target_type ?: 'Unknown')) . '</td>';
			echo '<td>' . esc_html($target_display) . '</td>';
			echo '<td>' . esc_html($countries_display) . '</td>';
			echo '<td><span class="' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span></td>';
			echo '<td>' . esc_html(date('M j, Y', strtotime($rule->post_date))) . '</td>';
			echo '<td>' . esc_html($clicks) . '</td>';
			echo '<td>';
			if ($source === 'elementor') {
				echo '<a href="#" onclick="egpEditElementorRule(' . $rule->ID . ')" class="button button-small">' . esc_html__('Edit in Elementor', 'elementor-geo-popup') . '</a> ';
			} else {
				echo '<a href="' . esc_url(get_edit_post_link($rule->ID)) . '" class="button button-small">' . esc_html__('Edit', 'elementor-geo-popup') . '</a> ';
			}
			echo '<a href="' . esc_url(get_delete_post_link($rule->ID)) . '" class="button button-small button-link-delete" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this rule?', 'elementor-geo-popup')) . '\')">' . esc_html__('Delete', 'elementor-geo-popup') . '</a>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody>';
		echo '</table>';
		
		// Add JavaScript for Elementor rule editing
		echo '<script>
		function egpEditElementorRule(ruleId) {
			// Find the Elementor element and open it in editor
			if (typeof elementor !== "undefined") {
				var element = elementor.getPreviewView().collection.findWhere({id: ruleId});
				if (element) {
					elementor.channels.editor.trigger("section:activated", element);
					elementor.channels.editor.trigger("panel:open:editor", element);
				} else {
					alert("Element not found in current page. Please navigate to the page containing this element.");
				}
			} else {
				alert("Elementor editor not available. Please open the page in Elementor editor.");
			}
		}
		</script>';
		
		echo '</div>';
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

	public function render_license() {
		do_action('egp_render_license_page');
	}
}


