<?php
/**
 * Admin Settings Page
 * 
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Settings Class
 */
class EGP_Admin_Settings {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_egp_update_database', array($this, 'ajax_update_database'));
        add_action('wp_ajax_egp_test_connection', array($this, 'ajax_test_connection'));
        

    }
    
    /**
     * Add admin menu
     */
    // Settings page will be rendered via top-level menu hook
    // Expose a render hook for the admin menu controller
    public function render_via_hook() {
        $this->render_settings_page();
    }



    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('egp_settings', 'egp_maxmind_license_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        // Do not register egp_database_path: it's managed programmatically by the updater.
        
        register_setting('egp_settings', 'egp_auto_update', array(
            'type' => 'boolean',
            'default' => false
        ));
        
        register_setting('egp_settings', 'egp_debug_mode', array(
            'type' => 'boolean',
            'default' => false
        ));
        
        register_setting('egp_settings', 'egp_default_popup_id', array(
            'type' => 'integer',
            'default' => 0
        ));
        
        register_setting('egp_settings', 'egp_fallback_behavior', array(
            'type' => 'string',
            'default' => 'show_to_all'
        ));
        
        register_setting('egp_settings', 'egp_preferred_countries', array(
            'type' => 'array',
            'default' => array('US', 'CA', 'GB')
        ));

        register_setting('egp_settings', 'egp_apply_preferred_to_untargeted', array(
            'type' => 'boolean',
            'default' => false
        ));

        // Cache mode: no_cache, cache_safe, cdn_vary
        register_setting('egp_settings', 'egp_cache_mode', array(
            'type' => 'string',
            'default' => 'no_cache'
        ));
        
        // Add settings sections
        add_settings_section(
            'egp_maxmind_section',
            '',
            array($this, 'render_maxmind_section'),
            'egp_settings_maxmind'
        );
        
        add_settings_section(
            'egp_general_section',
            '',
            array($this, 'render_general_section'),
            'egp_settings_general'
        );
        
        add_settings_section(
            'egp_preferred_countries_section',
            '',
            array($this, 'render_preferred_countries_section'),
            'egp_settings_preferred'
        );
        
        add_settings_section(
            'egp_database_section',
            '',
            array($this, 'render_database_section'),
            'egp_settings_database'
        );
        
        // Add settings fields
        add_settings_field(
            'egp_maxmind_license_key',
            __('MaxMind License Key', 'elementor-geo-popup'),
            array($this, 'render_license_key_field'),
            'egp_settings_maxmind',
            'egp_maxmind_section'
        );
        
        add_settings_field(
            'egp_auto_update',
            __('Auto Update Database', 'elementor-geo-popup'),
            array($this, 'render_auto_update_field'),
            'egp_settings_maxmind',
            'egp_maxmind_section'
        );
        
        add_settings_field(
            'egp_default_popup_id',
            __('Default Popup ID', 'elementor-geo-popup'),
            array($this, 'render_default_popup_field'),
            'egp_settings_general',
            'egp_general_section'
        );
        
        add_settings_field(
            'egp_fallback_behavior',
            __('Fallback Behavior', 'elementor-geo-popup'),
            array($this, 'render_fallback_field'),
            'egp_settings_general',
            'egp_general_section'
        );
        
        add_settings_field(
            'egp_debug_mode',
            __('Debug Mode', 'elementor-geo-popup'),
            array($this, 'render_debug_field'),
            'egp_settings_general',
            'egp_general_section'
        );

        add_settings_field(
            'egp_cache_mode',
            __('Cache Mode', 'elementor-geo-popup'),
            array($this, 'render_cache_mode_field'),
            'egp_settings',
            'egp_general_section'
        );

        add_settings_field(
            'egp_preferred_countries',
            __('Preferred Countries', 'elementor-geo-popup'),
            array($this, 'render_preferred_countries_field'),
            'egp_settings_preferred',
            'egp_preferred_countries_section'
        );

        add_settings_field(
            'egp_apply_preferred_to_untargeted',
            __('Apply preferred countries to untargeted popups', 'elementor-geo-popup'),
            array($this, 'render_apply_preferred_field'),
            'egp_settings_preferred',
            'egp_preferred_countries_section'
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Load styles on our top-level and sub pages
        $allowed = array(
            'toplevel_page_geo-elementor',
            'geo-elementor_page_elementor-geo-popup',
            'geo-elementor_page_geo-elementor-rules',
        );
        if (!in_array($hook, $allowed, true) && strpos($hook, 'geo-elementor_page_') === false) {
            return;
        }
        
        wp_enqueue_script(
            'egp-admin-script',
            EGP_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            EGP_VERSION,
            true
        );
        // Ensure jQuery UI tooltip is available for admin.js if used
        wp_enqueue_script('jquery-ui-tooltip');
        
        wp_enqueue_style(
            'egp-admin-style',
            EGP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            EGP_VERSION
        );
        
        wp_localize_script('egp-admin-script', 'egpAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('egp_admin_nonce'),
            'strings' => array(
                'updating' => __('Updating database...', 'elementor-geo-popup'),
                'success' => __('Database updated successfully!', 'elementor-geo-popup'),
                'error' => __('Error updating database.', 'elementor-geo-popup'),
                'testing' => __('Testing connection...', 'elementor-geo-popup'),
                'connectionSuccess' => __('Connection successful!', 'elementor-geo-popup'),
                'connectionError' => __('Connection failed.', 'elementor-geo-popup')
            )
        ));
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div class="wrap egp-settings">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                <span class="egp-admin-logo-wrap">
                    <img id="egp-admin-logo" src="<?php echo esc_url( EGP_PLUGIN_URL . 'assets/img/GeoElementor.svg' ); ?>" alt="Geo Elementor" style="height:40px;width:auto;vertical-align:middle;" onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';" />
                    <span class="egp-admin-logo-fallback" style="display:none;">GE</span>
                </span>
                <h1 style="margin:0;line-height:1;"><?php echo esc_html(get_admin_page_title()); ?></h1>
            </div>
			<?php if ( class_exists( 'EGP_Admin_Menu' ) ) { EGP_Admin_Menu::render_inner_nav( 'elementor-geo-popup' ); } ?>

			<div class="egp-hero">
				<h2><?php esc_html_e( 'GeoElementor Pro Extension Settings', 'elementor-geo-popup' ); ?></h2>
				<p><?php esc_html_e( 'Use this screen for advanced GeoElementor behavior. Geo Core remains the owner of shared geo engine and baseline routing.', 'elementor-geo-popup' ); ?></p>
				<div class="egp-chip-row">
					<span class="egp-chip"><?php esc_html_e( 'Geo Core: engine + baseline', 'elementor-geo-popup' ); ?></span>
					<span class="egp-chip"><?php esc_html_e( 'GeoElementor: advanced rules/groups', 'elementor-geo-popup' ); ?></span>
					<span class="egp-chip egp-chip--warn"><?php esc_html_e( 'Free limit remains 1 fallback + 1 mapping', 'elementor-geo-popup' ); ?></span>
				</div>
				<div class="egp-cta-row">
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-settings' ) ); ?>"><?php esc_html_e( 'Open Geo Core Settings', 'elementor-geo-popup' ); ?></a>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=geo-elementor-variants' ) ); ?>"><?php esc_html_e( 'Open Variant Groups', 'elementor-geo-popup' ); ?></a>
				</div>
			</div>

			<div class="notice notice-info" style="margin:14px 0;">
				<p>
					<?php esc_html_e( 'Baseline controls live in ReactWoo Geo Core (free) while GeoElementor focuses on advanced variant groups and element-level targeting (Pro).', 'elementor-geo-popup' ); ?>
					<br />
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-settings' ) ); ?>"><?php esc_html_e( 'Open Geo Core Settings', 'elementor-geo-popup' ); ?></a>
				</p>
			</div>
            
            <?php if (isset($_GET['settings-updated'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Settings saved successfully!', 'elementor-geo-popup'); ?></p>
                </div>
            <?php endif; ?>

			<div class="egp-section-card">
				<h2><?php esc_html_e( 'Feature Split', 'elementor-geo-popup' ); ?></h2>
				<table class="egp-feature-matrix">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Capability', 'elementor-geo-popup' ); ?></th>
							<th><?php esc_html_e( 'Geo Core', 'elementor-geo-popup' ); ?></th>
							<th><?php esc_html_e( 'GeoElementor', 'elementor-geo-popup' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Geo engine and MaxMind readiness', 'elementor-geo-popup' ); ?></td>
							<td><?php esc_html_e( 'Primary owner', 'elementor-geo-popup' ); ?></td>
							<td><?php esc_html_e( 'Depends on Geo Core', 'elementor-geo-popup' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Free page routing (server-side)', 'elementor-geo-popup' ); ?></td>
							<td><?php esc_html_e( '1 default + 1 additional country', 'elementor-geo-popup' ); ?></td>
							<td><?php esc_html_e( 'Extends decision path', 'elementor-geo-popup' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Variant Groups / multi-variant logic', 'elementor-geo-popup' ); ?></td>
							<td><?php esc_html_e( 'No', 'elementor-geo-popup' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'elementor-geo-popup' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
            
            <form method="post" action="options.php">
                <?php settings_fields('egp_settings'); ?>
                <div class="egp-section-card">
                    <h2><?php _e('General', 'elementor-geo-popup'); ?></h2>
                    <?php do_settings_sections('egp_settings_general'); ?>
                </div>
                <div class="egp-section-card" id="maxmind">
                    <h2><?php _e('Geo engine (ReactWoo Geo Core)', 'elementor-geo-popup'); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'GeoElementor now relies on the shared ReactWoo Geo Core plugin for IP-to-country lookups and MaxMind database management.', 'elementor-geo-popup' ); ?>
                    </p>
                    <p class="description">
                        <?php esc_html_e( 'Geo Core free baseline includes Elementor Page/Popup document-level geo visibility and page-level server-side routing (1 fallback + 1 country mapping per page). GeoElementor focuses on advanced controls like multi-variant routing, element-level rules, groups, and analytics.', 'elementor-geo-popup' ); ?>
                    </p>
                    <p class="description">
                        <?php esc_html_e( 'Configure your MaxMind Account ID, License Key, cache, and fallback country/currency in the ReactWoo Geo Core settings screen. GeoElementor will automatically reuse that geo engine.', 'elementor-geo-popup' ); ?>
                    </p>
                    <p class="description">
                        <?php esc_html_e( 'If Geo Core is missing or not ready, GeoElementor will gracefully fall back and your geo rules may not behave as expected.', 'elementor-geo-popup' ); ?>
                    </p>
                </div>
                <div class="egp-section-card">
                    <h2><?php _e('Preferred Countries', 'elementor-geo-popup'); ?></h2>
                    <?php do_settings_sections('egp_settings_preferred'); ?>
                </div>
                <div class="egp-section-card">
                    <h2><?php _e('Database Management', 'elementor-geo-popup'); ?></h2>
                    <p class="description">
                        <?php esc_html_e( 'Database downloads and updates are now handled entirely by ReactWoo Geo Core. Use the Geo Core → Tools screen to update or manually upload the MaxMind database.', 'elementor-geo-popup' ); ?>
                    </p>
                </div>
                <?php submit_button(); ?>
            </form>
            
            <div class="egp-database-actions">
                <h2><?php _e('Database Actions', 'elementor-geo-popup'); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'MaxMind database status, last update time, and manual upload options now live in ReactWoo Geo Core → Tools. Use that screen to manage the shared GeoLite2 database used by all ReactWoo products.', 'elementor-geo-popup' ); ?>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render MaxMind section
     */
    public function render_maxmind_section() {
        echo '<p>' . __('Configure your MaxMind integration to enable geolocation functionality.', 'elementor-geo-popup') . '</p>';
    }
    
    /**
     * Render general section
     */
    public function render_general_section() {
        echo '<p>' . __('Configure general plugin behavior and default settings.', 'elementor-geo-popup') . '</p>';
    }
    
    /**
     * Render database section
     */
    public function render_database_section() {
        echo '<p>' . __('Manage your MaxMind database and update settings.', 'elementor-geo-popup') . '</p>';
    }
    
    /**
     * Render license key field
     */
    public function render_license_key_field() {
        $value = get_option('egp_maxmind_license_key');
        ?>
        <input type="text" id="egp_maxmind_license_key" name="egp_maxmind_license_key" 
               value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description">
            <?php _e('Enter your MaxMind license key. You can get one for free at', 'elementor-geo-popup'); ?>
            <a href="https://www.maxmind.com/en/geolite2/signup" target="_blank">maxmind.com</a>
        </p>
        <?php
    }
    
    /**
     * Render auto update field
     */
    public function render_auto_update_field() {
        $value = get_option('egp_auto_update');
        ?>
        <label>
            <input type="checkbox" name="egp_auto_update" value="1" <?php checked($value, 1); ?> />
            <?php _e('Automatically update the database weekly', 'elementor-geo-popup'); ?>
        </label>
        <?php
    }
    
    /**
     * Render default popup field
     */
    public function render_default_popup_field() {
        $value = get_option('egp_default_popup_id');
        ?>
        <input type="number" id="egp_default_popup_id" name="egp_default_popup_id" 
               value="<?php echo esc_attr($value); ?>" min="0" />
        <p class="description">
            <?php _e('Default popup ID to show when no specific country match is found (0 = none)', 'elementor-geo-popup'); ?>
        </p>
        <?php
    }
    
    /**
     * Render fallback field
     */
    public function render_fallback_field() {
        $value = get_option('egp_fallback_behavior');
        $options = array(
            'show_to_none' => __('Show to none', 'elementor-geo-popup'),
            'show_to_all' => __('Show to all visitors', 'elementor-geo-popup'),
            'apply_group_rule' => __('Apply Group Rule', 'elementor-geo-popup'),
        );
        ?>
        <select name="egp_fallback_behavior">
            <?php foreach ($options as $key => $label) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
    
    /**
     * Render debug field
     */
    public function render_debug_field() {
        $value = get_option('egp_debug_mode');
        ?>
        <label>
            <input type="checkbox" name="egp_debug_mode" value="1" <?php checked($value, 1); ?> />
            <?php _e('Enable debug mode (logs geolocation data)', 'elementor-geo-popup'); ?>
        </label>
        <?php
    }

    /**
     * Render cache mode field
     */
    public function render_cache_mode_field() {
        $value = get_option('egp_cache_mode', 'no_cache');
        ?>
        <select name="egp_cache_mode">
            <option value="no_cache" <?php selected($value, 'no_cache'); ?>><?php esc_html_e('No-cache (server inject, optional CSS guard)', 'elementor-geo-popup'); ?></option>
            <option value="cache_safe" <?php selected($value, 'cache_safe'); ?>><?php esc_html_e('Cache-safe (AJAX country, JS guard only)', 'elementor-geo-popup'); ?></option>
            <option value="cdn_vary" <?php selected($value, 'cdn_vary'); ?>><?php esc_html_e('CDN Vary-by-country (server inject + Vary header)', 'elementor-geo-popup'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('Use Cache-safe if full-page caching/CDN is enabled without country vary.', 'elementor-geo-popup'); ?></p>
        <?php
    }

    /**
     * Render preferred countries section
     */
    public function render_preferred_countries_section() {
        ?>
        <p><?php _e('Configure preferred countries for global geo-targeting. These countries will be used as defaults in Elementor globals and widgets.', 'elementor-geo-popup'); ?></p>
        <?php
    }

    /**
     * Render preferred countries field
     */
    public function render_preferred_countries_field() {
        $value = get_option('egp_preferred_countries', array('US', 'CA', 'GB'));
        $countries = $this->get_countries_list();
        ?>
        <select name="egp_preferred_countries[]" multiple="multiple" style="width: 100%; min-height: 120px;">
            <?php foreach ($countries as $code => $name): ?>
                <option value="<?php echo esc_attr($code); ?>" <?php echo in_array($code, $value) ? 'selected="selected"' : ''; ?>>
                    <?php echo esc_html($name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php _e('Select countries that will be used as defaults for geo-targeting in Elementor globals and widgets. Hold Ctrl/Cmd to select multiple countries.', 'elementor-geo-popup'); ?></p>
        <?php
    }

    /**
     * Render apply preferred to untargeted field
     */
    public function render_apply_preferred_field() {
        $value = get_option('egp_apply_preferred_to_untargeted', false);
        ?>
        <label>
            <input type="checkbox" name="egp_apply_preferred_to_untargeted" value="1" <?php checked($value, 1); ?> />
            <?php _e('Only show popups without explicit EGP targeting to visitors from Preferred Countries', 'elementor-geo-popup'); ?>
        </label>
        <p class="description"><?php _e('Per‑popup targeting still overrides this. Default is off to avoid breaking existing popups.', 'elementor-geo-popup'); ?></p>
        <?php
    }

    /**
     * Get countries list
     */
    private function get_countries_list() {
        return array(
            'AF' => 'Afghanistan',
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'AS' => 'American Samoa',
            'AD' => 'Andorra',
            'AO' => 'Angola',
            'AI' => 'Anguilla',
            'AQ' => 'Antarctica',
            'AG' => 'Antigua and Barbuda',
            'AR' => 'Argentina',
            'AM' => 'Armenia',
            'AW' => 'Aruba',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'AZ' => 'Azerbaijan',
            'BS' => 'Bahamas',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BB' => 'Barbados',
            'BY' => 'Belarus',
            'BE' => 'Belgium',
            'BZ' => 'Belize',
            'BJ' => 'Benin',
            'BM' => 'Bermuda',
            'BT' => 'Bhutan',
            'BO' => 'Bolivia',
            'BA' => 'Bosnia and Herzegovina',
            'BW' => 'Botswana',
            'BV' => 'Bouvet Island',
            'BR' => 'Brazil',
            'IO' => 'British Indian Ocean Territory',
            'BN' => 'Brunei Darussalam',
            'BG' => 'Bulgaria',
            'BF' => 'Burkina Faso',
            'BI' => 'Burundi',
            'KH' => 'Cambodia',
            'CM' => 'Cameroon',
            'CA' => 'Canada',
            'CV' => 'Cape Verde',
            'KY' => 'Cayman Islands',
            'CF' => 'Central African Republic',
            'TD' => 'Chad',
            'CL' => 'Chile',
            'CN' => 'China',
            'CX' => 'Christmas Island',
            'CC' => 'Cocos (Keeling) Islands',
            'CO' => 'Colombia',
            'KM' => 'Comoros',
            'CG' => 'Congo',
            'CD' => 'Congo, the Democratic Republic of the',
            'CK' => 'Cook Islands',
            'CR' => 'Costa Rica',
            'CI' => 'Cote D\'Ivoire',
            'HR' => 'Croatia (Hrvatska)',
            'CU' => 'Cuba',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'DK' => 'Denmark',
            'DJ' => 'Djibouti',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'EC' => 'Ecuador',
            'EG' => 'Egypt',
            'SV' => 'El Salvador',
            'GQ' => 'Equatorial Guinea',
            'ER' => 'Eritrea',
            'EE' => 'Estonia',
            'ET' => 'Ethiopia',
            'FK' => 'Falkland Islands (Malvinas)',
            'FO' => 'Faroe Islands',
            'FJ' => 'Fiji',
            'FI' => 'Finland',
            'FR' => 'France',
            'GF' => 'French Guiana',
            'PF' => 'French Polynesia',
            'TF' => 'French Southern Territories',
            'GA' => 'Gabon',
            'GM' => 'Gambia',
            'GE' => 'Georgia',
            'DE' => 'Germany',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GR' => 'Greece',
            'GL' => 'Greenland',
            'GD' => 'Grenada',
            'GP' => 'Guadeloupe',
            'GU' => 'Guam',
            'GT' => 'Guatemala',
            'GN' => 'Guinea',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HT' => 'Haiti',
            'HM' => 'Heard Island and Mcdonald Islands',
            'VA' => 'Holy See (Vatican City State)',
            'HN' => 'Honduras',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'IS' => 'Iceland',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IR' => 'Iran, Islamic Republic of',
            'IQ' => 'Iraq',
            'IE' => 'Ireland',
            'IL' => 'Israel',
            'IT' => 'Italy',
            'JM' => 'Jamaica',
            'JP' => 'Japan',
            'JO' => 'Jordan',
            'KZ' => 'Kazakhstan',
            'KE' => 'Kenya',
            'KI' => 'Kiribati',
            'KP' => 'Korea, Democratic People\'s Republic of',
            'KR' => 'Korea, Republic of',
            'KW' => 'Kuwait',
            'KG' => 'Kyrgyzstan',
            'LA' => 'Lao People\'s Democratic Republic',
            'LV' => 'Latvia',
            'LB' => 'Lebanon',
            'LS' => 'Lesotho',
            'LR' => 'Liberia',
            'LY' => 'Libyan Arab Jamahiriya',
            'LI' => 'Liechtenstein',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MO' => 'Macao',
            'MK' => 'Macedonia, the Former Yugoslav Republic of',
            'MG' => 'Madagascar',
            'MW' => 'Malawi',
            'MY' => 'Malaysia',
            'MV' => 'Maldives',
            'ML' => 'Mali',
            'MT' => 'Malta',
            'MH' => 'Marshall Islands',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MU' => 'Mauritius',
            'YT' => 'Mayotte',
            'MX' => 'Mexico',
            'FM' => 'Micronesia, Federated States of',
            'MD' => 'Moldova, Republic of',
            'MC' => 'Monaco',
            'MN' => 'Mongolia',
            'MS' => 'Montserrat',
            'MA' => 'Morocco',
            'MZ' => 'Mozambique',
            'MM' => 'Myanmar',
            'NA' => 'Namibia',
            'NR' => 'Nauru',
            'NP' => 'Nepal',
            'NL' => 'Netherlands',
            'NC' => 'New Caledonia',
            'NZ' => 'New Zealand',
            'NI' => 'Nicaragua',
            'NE' => 'Niger',
            'NG' => 'Nigeria',
            'NU' => 'Niue',
            'NF' => 'Norfolk Island',
            'MP' => 'Northern Mariana Islands',
            'NO' => 'Norway',
            'OM' => 'Oman',
            'PK' => 'Pakistan',
            'PW' => 'Palau',
            'PS' => 'Palestinian Territory, Occupied',
            'PA' => 'Panama',
            'PG' => 'Papua New Guinea',
            'PY' => 'Paraguay',
            'PE' => 'Peru',
            'PH' => 'Philippines',
            'PN' => 'Pitcairn',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'PR' => 'Puerto Rico',
            'QA' => 'Qatar',
            'RE' => 'Reunion',
            'RO' => 'Romania',
            'RU' => 'Russian Federation',
            'RW' => 'Rwanda',
            'SH' => 'Saint Helena',
            'KN' => 'Saint Kitts and Nevis',
            'LC' => 'Saint Lucia',
            'PM' => 'Saint Pierre and Miquelon',
            'VC' => 'Saint Vincent and the Grenadines',
            'WS' => 'Samoa',
            'SM' => 'San Marino',
            'ST' => 'Sao Tome and Principe',
            'SA' => 'Saudi Arabia',
            'SN' => 'Senegal',
            'SC' => 'Seychelles',
            'SL' => 'Sierra Leone',
            'SG' => 'Singapore',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'SB' => 'Solomon Islands',
            'SO' => 'Somalia',
            'ZA' => 'South Africa',
            'GS' => 'South Georgia and the South Sandwich Islands',
            'ES' => 'Spain',
            'LK' => 'Sri Lanka',
            'SD' => 'Sudan',
            'SR' => 'Suriname',
            'SJ' => 'Svalbard and Jan Mayen',
            'SZ' => 'Swaziland',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'SY' => 'Syrian Arab Republic',
            'TW' => 'Taiwan, Province of China',
            'TJ' => 'Tajikistan',
            'TZ' => 'Tanzania, United Republic of',
            'TH' => 'Thailand',
            'TL' => 'Timor-Leste',
            'TG' => 'Togo',
            'TK' => 'Tokelau',
            'TO' => 'Tonga',
            'TT' => 'Trinidad and Tobago',
            'TN' => 'Tunisia',
            'TR' => 'Turkey',
            'TM' => 'Turkmenistan',
            'TC' => 'Turks and Caicos Islands',
            'TV' => 'Tuvalu',
            'UG' => 'Uganda',
            'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'UM' => 'United States Minor Outlying Islands',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VU' => 'Vanuatu',
            'VE' => 'Venezuela',
            'VN' => 'Vietnam',
            'VG' => 'Virgin Islands, British',
            'VI' => 'Virgin Islands, U.S.',
            'WF' => 'Wallis and Futuna',
            'EH' => 'Western Sahara',
            'YE' => 'Yemen',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
        );
    }
    
    /**
     * AJAX update database
     */
    public function ajax_update_database() {
        check_ajax_referer('egp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $license_key = get_option('egp_maxmind_license_key');
        if (empty($license_key)) {
            wp_send_json_error(__('MaxMind license key not configured', 'elementor-geo-popup'));
        }
        
        $result = $this->update_geo_database($license_key);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('Database updated successfully', 'elementor-geo-popup'));
    }
    
    /**
     * AJAX test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('egp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $license_key = get_option('egp_maxmind_license_key');
        if (empty($license_key)) {
            wp_send_json_error(__('MaxMind license key not configured', 'elementor-geo-popup'));
        }
        
        $result = $this->test_maxmind_connection($license_key);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('Connection successful', 'elementor-geo-popup'));
    }
    
    /**
     * Update geo database
     */
    private function update_geo_database($license_key) {
        if (!class_exists('PharData')) {
            return new WP_Error('phar_missing', __('The PHP Phar extension is required to extract the MaxMind archive. Please enable the phar extension.', 'elementor-geo-popup'));
        }

        $upload_dir = wp_upload_dir();
        $geo_dir = $upload_dir['basedir'] . '/geo-popup-db';

        if (!file_exists($geo_dir)) {
            wp_mkdir_p($geo_dir);
        }

        // Prepare temp paths
        $temp_gz = trailingslashit($geo_dir) . 'GeoLite2-Country.tar.gz';
        $temp_tar = trailingslashit($geo_dir) . 'GeoLite2-Country.tar';
        $extract_dir = trailingslashit($geo_dir) . 'extract_tmp';

        // Clean any previous temp
        if (file_exists($temp_gz)) { @unlink($temp_gz); }
        if (file_exists($temp_tar)) { @unlink($temp_tar); }
        if (file_exists($extract_dir)) { $this->rmdir_recursive($extract_dir); }

        $download_url = add_query_arg(array(
            'edition_id' => 'GeoLite2-Country',
            'license_key' => $license_key,
            'suffix' => 'tar.gz'
        ), 'https://download.maxmind.com/app/geoip_download');

        // Stream download to disk to avoid memory spikes
        $response = wp_remote_get($download_url, array(
            'timeout'  => 60,
            'stream'   => true,
            'filename' => $temp_gz,
            'headers'  => array('User-Agent' => 'Elementor-Geo-Popup')
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200 || !file_exists($temp_gz) || filesize($temp_gz) === 0) {
            return new WP_Error('download_failed', sprintf(__('Failed to download MaxMind database (HTTP %d).', 'elementor-geo-popup'), intval($code)));
        }

        try {
            // Decompress .tar.gz to .tar
            $gz = new PharData($temp_gz);
            $gz->decompress(); // creates $temp_tar

            // Extract .tar to temp directory
            if (!file_exists($extract_dir)) {
                wp_mkdir_p($extract_dir);
            }
            $tar = new PharData($temp_tar);
            $tar->extractTo($extract_dir, null, true);
        } catch (Exception $e) {
            // Clean temp and return error
            if (file_exists($temp_gz)) { @unlink($temp_gz); }
            if (file_exists($temp_tar)) { @unlink($temp_tar); }
            if (file_exists($extract_dir)) { $this->rmdir_recursive($extract_dir); }
            return new WP_Error('extract_failed', sprintf(__('Failed to extract archive: %s', 'elementor-geo-popup'), $e->getMessage()));
        }

        // Find the .mmdb file inside the extracted directory
        $mmdb_file = null;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extract_dir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION)) === 'mmdb') {
                $mmdb_file = $file->getPathname();
                break;
            }
        }

        if (!$mmdb_file) {
            // Clean temp
            if (file_exists($temp_gz)) { @unlink($temp_gz); }
            if (file_exists($temp_tar)) { @unlink($temp_tar); }
            if (file_exists($extract_dir)) { $this->rmdir_recursive($extract_dir); }
            return new WP_Error('no_mmdb', __('No .mmdb file found in the downloaded archive.', 'elementor-geo-popup'));
        }

        // Move to final location
        $final_path = trailingslashit($geo_dir) . 'GeoLite2-Country.mmdb';
        if (file_exists($final_path)) {
            @unlink($final_path);
        }
        if (!@rename($mmdb_file, $final_path)) {
            // Try copy fallback
            if (!@copy($mmdb_file, $final_path)) {
                return new WP_Error('move_failed', __('Failed to move the database file to its final location.', 'elementor-geo-popup'));
            }
        }

        // Clean temp files but keep .htaccess and final .mmdb
        if (file_exists($temp_gz)) { @unlink($temp_gz); }
        if (file_exists($temp_tar)) { @unlink($temp_tar); }
        if (file_exists($extract_dir)) { $this->rmdir_recursive($extract_dir); }

        // Update options
        update_option('egp_database_path', $final_path);
        update_option('egp_last_update', current_time('mysql'));

        return true;
    }
    
    /**
     * Test MaxMind connection
     */
    private function test_maxmind_connection($license_key) {
        $test_url = add_query_arg(array(
            'edition_id' => 'GeoLite2-Country',
            'license_key' => $license_key,
            'suffix' => 'tar.gz'
        ), 'https://download.maxmind.com/app/geoip_download');
        
        $response = wp_remote_head($test_url);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new WP_Error('invalid_response', sprintf(__('Invalid response code: %d', 'elementor-geo-popup'), $status_code));
        }
        
        return true;
    }
    
    /**
     * Cleanup extracted files
     */
    private function cleanup_extracted_files($directory) {
        // Deprecated in favor of rmdir_recursive on a specific temp folder.
        return;
    }

    private function rmdir_recursive($path) {
        if (!file_exists($path)) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $fileinfo) {
            if ($fileinfo->isDir()) {
                @rmdir($fileinfo->getRealPath());
            } else {
                @unlink($fileinfo->getRealPath());
            }
        }
        @rmdir($path);
    }
}

