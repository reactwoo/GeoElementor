<?php
/**
 * Licensing System Integration
 * 
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Licensing System Class for Geo Elementor Plugin
 */
class EGP_Licensing {
    
    private $license_manager;
    private $plugin_slug = 'geo-elementor';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Try to initialize centralized license manager, fallback to basic if it fails
        try {
            $this->license_manager = EGP_Centralized_License_Manager::get_instance();
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EGP License: Centralized manager failed, using fallback: ' . $e->getMessage());
            }
            $this->license_manager = null;
        }
        
        add_action('admin_init', array($this, 'init_licensing'));
        add_action('admin_notices', array($this, 'license_notices'));
        add_action('wp_ajax_egp_activate_license', array($this, 'ajax_activate_license'));
        add_action('wp_ajax_egp_deactivate_license', array($this, 'ajax_deactivate_license'));
        add_action('wp_ajax_egp_check_license', array($this, 'ajax_check_license'));
        add_action('wp_ajax_egp_force_clear_license', array($this, 'ajax_force_clear_license'));
        
        // Schedule license checks (now handled centrally)
        if (!wp_next_scheduled('egp_daily_license_check')) {
            wp_schedule_event(time(), 'daily', 'egp_daily_license_check');
        }
        add_action('egp_daily_license_check', array($this, 'check_license_status'));
        
        // Allow rendering the license page directly without redirects
        add_action('egp_render_license_page', array($this, 'render_license_page'));
    }
    
    /**
     * Initialize licensing
     */
    public function init_licensing() {
        // Check license status on admin pages
        if (is_admin()) {
            $this->check_license_status();
        }
    }
    
    /**
     * Check license status
     */
    public function check_license_status() {
        if (!$this->license_manager) {
            return false;
        }
        
        // Check and refresh tokens before validation
        $this->license_manager->check_and_refresh_tokens($this->plugin_slug);
        
        $license_data = $this->license_manager->get_license_data($this->plugin_slug, null, true);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EGP License: Centralized manager returned: ' . print_r($license_data, true));
        }
        
        if (is_wp_error($license_data)) {
            $error_message = $license_data->get_error_message();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EGP License: License check failed: ' . $error_message);
            }
            
            // If it's a token expiration error, clear the expired tokens
            if (strpos($error_message, 'expired') !== false || strpos($error_message, 'Invalid or expired token') !== false) {
                $this->license_manager->clear_expired_tokens($this->plugin_slug);
                
                // Also clear old format options
                delete_option('egp_license_access_token');
                delete_option('egp_license_refresh_token');
                delete_option('egp_license_expires_at');
                delete_option('egp_license_data');
                delete_option('egp_license_status');
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('EGP License: Cleared expired tokens and old options');
                }
            }
            
            return false;
        }
        
        $is_valid = isset($license_data['valid']) && $license_data['valid'];
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EGP License: Validation result - is_valid: ' . ($is_valid ? 'true' : 'false'));
        }
        
        if ($is_valid) {
            update_option('egp_license_status', 'valid');
            
            // Only store license data if it contains the formatted fields for display
            if (isset($license_data['product_name'])) {
                update_option('egp_license_data', $license_data);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EGP License: Status updated to valid');
            }
        } else {
            $error_message = isset($license_data['error']) ? $license_data['error'] : 'Unknown error';
            update_option('egp_license_status', 'invalid');
            update_option('egp_license_error', $error_message);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EGP License: Status updated to invalid - ' . $error_message);
            }
        }
        
        return $is_valid;
    }
    
    /**
     * Activate license
     */
    public function activate_license($license_key) {
        if (!$this->license_manager) {
            return new WP_Error('no_manager', 'License manager not available');
        }
        
        $domain = wp_parse_url(get_site_url(), PHP_URL_HOST);
        $plugin_version = $this->get_plugin_version();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EGP License: Attempting to activate license key: " . substr($license_key, 0, 8) . "...");
            error_log("EGP License: Starting activation process for domain: " . $domain);
            error_log("EGP License: Plugin version: " . $plugin_version);
        }
        
        // Use centralized license manager
        $result = $this->license_manager->activate_license(
            $this->plugin_slug,
            $license_key,
            $domain,
            $plugin_version,
            'geo'
        );
        
        if (is_wp_error($result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("EGP License: Activation failed with error: " . $result->get_error_message());
            }
            return $result;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EGP License: Activation successful");
        }
        
        // Store license data using centralized manager for consistency
        if (isset($result['success']) && $result['success']) {
            $this->license_manager->store_license_data($this->plugin_slug, $result);
        }
        
        return $result;
    }
    
    /**
     * Deactivate license
     */
    public function deactivate_license() {
        // Clear all license data
        delete_option('egp_license_key');
        delete_option('egp_license_status');
        delete_option('egp_license_data');
        delete_option('egp_license_error');
        delete_option('egp_license_access_token');
        delete_option('egp_license_refresh_token');
        delete_option('egp_license_expires_at');
        
        // Also clear centralized manager data
        if ($this->license_manager && method_exists($this->license_manager, 'clear_expired_tokens')) {
            $this->license_manager->clear_expired_tokens($this->plugin_slug);
        }
        
        return true;
    }
    
    /**
     * Force clear all license data (useful for troubleshooting)
     */
    public function force_clear_license_data() {
        // Clear all license-related options
        delete_option('egp_license_key');
        delete_option('egp_license_status');
        delete_option('egp_license_data');
        delete_option('egp_license_error');
        delete_option('egp_license_access_token');
        delete_option('egp_license_refresh_token');
        delete_option('egp_license_expires_at');
        
        // Also clear the centralized manager's data
        if ($this->license_manager && method_exists($this->license_manager, 'clear_expired_tokens')) {
            $this->license_manager->clear_expired_tokens($this->plugin_slug);
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EGP License: Force cleared all license data');
        }
        
        return true;
    }
    
    /**
     * Get plugin version for activation
     */
    private function get_plugin_version() {
        if (defined('EGP_VERSION')) {
            return EGP_VERSION;
        }
        // Fallback to a default if EGP_VERSION is not defined
        return '1.0.0';
    }
    
    /**
     * AJAX activate license
     */
    public function ajax_activate_license() {
        check_ajax_referer('egp_license_nonce', 'nonce');
        
        $required_cap = defined('EGP_LICENSE_CAPABILITY') ? EGP_LICENSE_CAPABILITY : 'manage_options';
        if (!current_user_can($required_cap)) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $license_key = sanitize_text_field($_POST['license_key']);
        if (empty($license_key)) {
            wp_send_json_error(__('License key is required', 'elementor-geo-popup'));
        }
        
        $result = $this->activate_license($license_key);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        // Save the license key locally
        update_option('egp_license_key', $license_key);
        
        wp_send_json_success(__('License activated successfully', 'elementor-geo-popup'));
    }
    
    /**
     * AJAX deactivate license
     */
    public function ajax_deactivate_license() {
        check_ajax_referer('egp_license_nonce', 'nonce');
        
        $required_cap = defined('EGP_LICENSE_CAPABILITY') ? EGP_LICENSE_CAPABILITY : 'manage_options';
        if (!current_user_can($required_cap)) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $this->deactivate_license();
        
        wp_send_json_success(__('License deactivated successfully', 'elementor-geo-popup'));
    }
    
    /**
     * AJAX check license
     */
    public function ajax_check_license() {
        check_ajax_referer('egp_license_nonce', 'nonce');
        
        $required_cap = defined('EGP_LICENSE_CAPABILITY') ? EGP_LICENSE_CAPABILITY : 'manage_options';
        if (!current_user_can($required_cap)) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $this->check_license_status();
        
        wp_send_json_success(__('License status updated', 'elementor-geo-popup'));
    }
    
    /**
     * AJAX force clear license data
     */
    public function ajax_force_clear_license() {
        check_ajax_referer('egp_license_nonce', 'nonce');
        
        $required_cap = defined('EGP_LICENSE_CAPABILITY') ? EGP_LICENSE_CAPABILITY : 'manage_options';
        if (!current_user_can($required_cap)) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $this->force_clear_license_data();
        
        wp_send_json_success(__('License data force cleared successfully', 'elementor-geo-popup'));
    }
    
    /**
     * Render license page
     */
    public function render_license_page() {
        // Check if user has access to either the Settings page or our custom menu
        $allowed_caps = array('manage_options', 'manage_woocommerce');
        $allowed_caps = apply_filters('egp_allowed_capabilities', $allowed_caps);
        
        $user_has_access = false;
        foreach ($allowed_caps as $cap) {
            if (current_user_can($cap)) {
                $user_has_access = true;
                break;
            }
        }
        
        if (!$user_has_access) {
            // Allow custom capability override as last resort
            $custom_cap = apply_filters('egp_license_capability', 'manage_options');
            if (!current_user_can($custom_cap)) {
                wp_die(__('You do not have sufficient permissions to access this page.', 'elementor-geo-popup'));
            }
        }

        // Determine which capability to use for AJAX actions
        $ajax_cap = current_user_can('manage_options') ? 'manage_options' : 'manage_woocommerce';
        if (has_filter('egp_license_capability')) {
            $ajax_cap = apply_filters('egp_license_capability', 'manage_options');
        }

        // Store the capability for AJAX checks
        if (!defined('EGP_LICENSE_CAPABILITY')) {
            define('EGP_LICENSE_CAPABILITY', $ajax_cap);
        }
        
        // Debug: Log the capability being used
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EGP License Page: User capability check - Required: ' . $ajax_cap . ', User can: ' . (current_user_can($ajax_cap) ? 'YES' : 'NO'));
        }
        
        $license_key = get_option('egp_license_key', '');
        $license_status = get_option('egp_license_status', '');
        $license_data = get_option('egp_license_data', array());
        
        // Debug: Log what we're getting for license data
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EGP License Page: Retrieved license data: ' . print_r($license_data, true));
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Elementor Geo Popup License', 'elementor-geo-popup'); ?></h1>
            
            <?php if (isset($_GET['license_updated'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('License settings updated successfully!', 'elementor-geo-popup'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="egp-license-container">
                <div class="egp-license-info">
                    <h2><?php _e('License Information', 'elementor-geo-popup'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('License Key', 'elementor-geo-popup'); ?></th>
                            <td>
                                <input type="text" id="egp-license-key" class="regular-text" 
                                       value="<?php echo esc_attr($license_key); ?>" 
                                       placeholder="<?php _e('Enter your license key', 'elementor-geo-popup'); ?>" />
                                <p class="description">
                                    <?php _e('Enter your license key from reactwoo.com', 'elementor-geo-popup'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('License Status', 'elementor-geo-popup'); ?></th>
                            <td>
                                <span class="egp-license-status egp-status-<?php echo esc_attr($license_status); ?>">
                                    <?php if ($license_status === 'valid') : ?>
                                        <span style="color: green;">✓ <?php _e('Valid', 'elementor-geo-popup'); ?></span>
                                    <?php else : ?>
                                        <?php echo $this->get_license_status_text($license_status); ?>
                                    <?php endif; ?>
                                </span>
                            </td>
                        </tr>
                        <?php if (!empty($license_data)) : ?>
                        <tr>
                            <th scope="row"><?php _e('Product', 'elementor-geo-popup'); ?></th>
                            <td>
                                <?php 
                                $product_name = $license_data['product_name'] ?? '';
                                $is_free_plan = $license_data['is_free_plan'] ?? false;
                                
                                if ($product_name) {
                                    if ($is_free_plan) {
                                        echo '<strong>' . esc_html($product_name) . '</strong> <span style="color: #0073aa;">(Free)</span>';
                                        echo '<br><a href="https://reactwoo.com/geo-elementor" target="_blank" class="button button-secondary" style="margin-top: 5px;">Upgrade to Pro</a>';
                                    } else {
                                        echo '<strong>' . esc_html($product_name) . '</strong>';
                                    }
                                } else {
                                    echo '<em>' . __('Not available', 'elementor-geo-popup') . '</em>';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Expires', 'elementor-geo-popup'); ?></th>
                            <td>
                                <?php 
                                if (isset($license_data['is_free_plan']) && $license_data['is_free_plan']) {
                                    echo '<span style="color: #0073aa;">' . __('Never Expires', 'elementor-geo-popup') . '</span>';
                                } elseif (isset($license_data['expires_at']) && $license_data['expires_at']) {
                                    $expires_date = date('F j, Y', $license_data['expires_at']);
                                    echo esc_html($expires_date);
                                } else {
                                    echo '<em>' . __('Not available', 'elementor-geo-popup') . '</em>';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Sites', 'elementor-geo-popup'); ?></th>
                            <td>
                                <?php 
                                if (isset($license_data['is_free_plan']) && $license_data['is_free_plan']) {
                                    echo '<span style="color: #0073aa;">1 / Unlimited</span>';
                                } elseif (isset($license_data['sites_text'])) {
                                    echo esc_html($license_data['sites_text']);
                                } else {
                                    echo '<em>' . __('Not available', 'elementor-geo-popup') . '</em>';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Version', 'elementor-geo-popup'); ?></th>
                            <td><?php echo esc_html($license_data['version'] ?? '1.0.0'); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                
                <div class="egp-license-actions">
                    <h2><?php _e('License Actions', 'elementor-geo-popup'); ?></h2>
                    
                    <div class="egp-action-buttons">
                        <button type="button" id="egp-activate-license" class="button button-primary">
                            <?php _e('Activate License', 'elementor-geo-popup'); ?>
                        </button>
                        
                        <button type="button" id="egp-deactivate-license" class="button button-secondary">
                            <?php _e('Deactivate License', 'elementor-geo-popup'); ?>
                        </button>
                        
                        <button type="button" id="egp-check-license" class="button button-secondary">
                            <?php _e('Check License', 'elementor-geo-popup'); ?>
                        </button>
                        
                        <button type="button" id="egp-force-clear-license" class="button button-link-delete" style="margin-left: 10px;">
                            <?php _e('Force Clear License Data', 'elementor-geo-popup'); ?>
                        </button>
                    </div>
                    
                    <div id="egp-license-message"></div>
                </div>
                
                <div class="egp-license-help">
                    <h2><?php _e('Need Help?', 'elementor-geo-popup'); ?></h2>
                    <p>
                        <?php _e('If you need help with your license or have questions, please contact us at', 'elementor-geo-popup'); ?>
                        <a href="mailto:support@reactwoo.com">support@reactwoo.com</a>
                    </p>
                    <p>
                        <?php _e('You can also visit our website at', 'elementor-geo-popup'); ?>
                        <a href="https://reactwoo.com" target="_blank">reactwoo.com</a>
                    </p>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Activate license
            $('#egp-activate-license').on('click', function() {
                var licenseKey = $('#egp-license-key').val();
                if (!licenseKey) {
                    alert('<?php _e('Please enter a license key', 'elementor-geo-popup'); ?>');
                    return;
                }
                
                $(this).prop('disabled', true).text('<?php _e('Activating...', 'elementor-geo-popup'); ?>');
                
                $.post(ajaxurl, {
                    action: 'egp_activate_license',
                    license_key: licenseKey,
                    nonce: '<?php echo wp_create_nonce('egp_license_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        $('#egp-license-message').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                    $('#egp-activate-license').prop('disabled', false).text('<?php _e('Activate License', 'elementor-geo-popup'); ?>');
                });
            });
            
            // Deactivate license
            $('#egp-deactivate-license').on('click', function() {
                if (!confirm('<?php _e('Are you sure you want to deactivate your license?', 'elementor-geo-popup'); ?>')) {
                    return;
                }
                
                $(this).prop('disabled', true).text('<?php _e('Deactivating...', 'elementor-geo-popup'); ?>');
                
                $.post(ajaxurl, {
                    action: 'egp_deactivate_license',
                    nonce: '<?php echo wp_create_nonce('egp_license_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        $('#egp-license-message').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                    $('#egp-deactivate-license').prop('disabled', false).text('<?php _e('Deactivate License', 'elementor-geo-popup'); ?>');
                });
            });
            
            // Check license
            $('#egp-check-license').on('click', function() {
                $(this).prop('disabled', true).text('<?php _e('Checking...', 'elementor-geo-popup'); ?>');
                
                $.post(ajaxurl, {
                    action: 'egp_check_license',
                    nonce: '<?php echo wp_create_nonce('egp_license_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        $('#egp-license-message').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                    $('#egp-check-license').prop('disabled', false).text('<?php _e('Check License', 'elementor-geo-popup'); ?>');
                });
            });
            
            // Force clear license data
            $('#egp-force-clear-license').on('click', function() {
                if (!confirm('<?php _e('This will completely clear all license data. This action cannot be undone. Are you sure?', 'elementor-geo-popup'); ?>')) {
                    return;
                }
                
                $(this).prop('disabled', true).text('<?php _e('Clearing...', 'elementor-geo-popup'); ?>');
                
                $.post(ajaxurl, {
                    action: 'egp_force_clear_license',
                    nonce: '<?php echo wp_create_nonce('egp_license_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#egp-license-message').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $('#egp-license-message').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                    $('#egp-force-clear-license').prop('disabled', false).text('<?php _e('Force Clear License Data', 'elementor-geo-popup'); ?>');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get license status text
     */
    private function get_license_status_text($status) {
        switch ($status) {
            case 'valid':
                return __('Valid', 'elementor-geo-popup');
            case 'invalid':
                return __('Invalid', 'elementor-geo-popup');
            case 'expired':
                return __('Expired', 'elementor-geo-popup');
            default:
                return __('Unknown', 'elementor-geo-popup');
        }
    }
    
    /**
     * Display license notices
     */
    public function license_notices() {
        // Only show on admin pages
        if (!is_admin()) {
            return;
        }
        
        $license_status = get_option('egp_license_status', '');
        
        if ($license_status === 'invalid' || $license_status === 'expired') {
            $message = __('Your Elementor Geo Popup license is invalid or expired. Please <a href="%s">activate your license</a> to continue using all features.', 'elementor-geo-popup');
            $license_page_url = admin_url('admin.php?page=geo-elementor-license');
            
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>' . sprintf($message, $license_page_url) . '</p>';
            echo '</div>';
        }
    }
}



