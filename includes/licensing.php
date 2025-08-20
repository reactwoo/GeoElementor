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
 * Licensing System Class
 */
class EGP_Licensing {
    
    /**
     * License server URL
     */
    private $license_server = 'https://license.reactwoo.com';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'init_licensing'));
        add_action('admin_notices', array($this, 'license_notices'));
        add_action('wp_ajax_egp_activate_license', array($this, 'ajax_activate_license'));
        add_action('wp_ajax_egp_deactivate_license', array($this, 'ajax_deactivate_license'));
        add_action('wp_ajax_egp_check_license', array($this, 'ajax_check_license'));
        
        // Schedule license checks
        if (!wp_next_scheduled('egp_daily_license_check')) {
            wp_schedule_event(time(), 'daily', 'egp_daily_license_check');
        }
        add_action('egp_daily_license_check', array($this, 'check_license_status'));
    }
    
    /**
     * Initialize licensing
     */
    public function init_licensing() {
        // Add license settings to the admin settings page
        add_action('admin_menu', array($this, 'add_license_menu'));
        
        // Check license status on admin pages
        if (is_admin()) {
            $this->check_license_status();
        }
    }
    
    /**
     * Add license menu
     */
    public function add_license_menu() {
        add_submenu_page(
            'options-general.php',
            __('Elementor Geo Popup License', 'elementor-geo-popup'),
            __('EGP License', 'elementor-geo-popup'),
            'manage_options',
            'egp-license',
            array($this, 'render_license_page')
        );
    }
    
    /**
     * Render license page
     */
    public function render_license_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $license_key = get_option('egp_license_key', '');
        $license_status = get_option('egp_license_status', '');
        $license_data = get_option('egp_license_data', array());
        
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
                                    <?php echo $this->get_license_status_text($license_status); ?>
                                </span>
                            </td>
                        </tr>
                        <?php if (!empty($license_data)) : ?>
                        <tr>
                            <th scope="row"><?php _e('Product', 'elementor-geo-popup'); ?></th>
                            <td><?php echo esc_html($license_data['product_name'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Expires', 'elementor-geo-popup'); ?></th>
                            <td><?php echo esc_html($license_data['expires'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Sites', 'elementor-geo-popup'); ?></th>
                            <td><?php echo esc_html($license_data['sites_count'] ?? ''); ?> / <?php echo esc_html($license_data['sites_limit'] ?? ''); ?></td>
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
                return '<span style="color: green;">✓ ' . __('Valid', 'elementor-geo-popup') . '</span>';
            case 'invalid':
                return '<span style="color: red;">✗ ' . __('Invalid', 'elementor-geo-popup') . '</span>';
            case 'expired':
                return '<span style="color: orange;">⚠ ' . __('Expired', 'elementor-geo-popup') . '</span>';
            case 'inactive':
                return '<span style="color: gray;">○ ' . __('Inactive', 'elementor-geo-popup') . '</span>';
            default:
                return '<span style="color: gray;">○ ' . __('Not Set', 'elementor-geo-popup') . '</span>';
        }
    }
    
    /**
     * AJAX activate license
     */
    public function ajax_activate_license() {
        check_ajax_referer('egp_license_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
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
        
        wp_send_json_success(__('License activated successfully', 'elementor-geo-popup'));
    }
    
    /**
     * AJAX deactivate license
     */
    public function ajax_deactivate_license() {
        check_ajax_referer('egp_license_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $result = $this->deactivate_license();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('License deactivated successfully', 'elementor-geo-popup'));
    }
    
    /**
     * AJAX check license
     */
    public function ajax_check_license() {
        check_ajax_referer('egp_license_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $result = $this->check_license_status();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('License status updated', 'elementor-geo-popup'));
    }
    
    /**
     * Activate license
     */
    private function activate_license($license_key) {
        $site_url = get_site_url();
        $site_name = get_bloginfo('name');
        
        // Token-based activation (Option B)
        $response = wp_remote_post($this->license_server . '/activate', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode(array(
                'licenseKey' => $license_key,
                'domain' => wp_parse_url($site_url, PHP_URL_HOST),
                'pluginVersion' => defined('EGP_VERSION') ? EGP_VERSION : '1.0.0',
                'productType' => 'geo'
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data) {
            return new WP_Error('invalid_response', __('Invalid response from license server', 'elementor-geo-popup'));
        }
        
        $access_token = $data['accessToken'] ?? $data['access_token'] ?? ($data['data']['access_token'] ?? '');
        $refresh_token = $data['refreshToken'] ?? $data['refresh_token'] ?? ($data['data']['refresh_token'] ?? '');
        $expires_at = intval($data['expires_at'] ?? ($data['data']['expires_at'] ?? 0));
        
        if (empty($access_token) || empty($refresh_token)) {
            return new WP_Error('activation_failed', __('License activation failed', 'elementor-geo-popup'));
        }
        
        // Store license data and tokens
        update_option('egp_license_key', $license_key);
        update_option('egp_license_access_token', $access_token);
        update_option('egp_license_refresh_token', $refresh_token);
        update_option('egp_license_expires_at', $expires_at);
        update_option('egp_license_status', 'valid');
        
        // Fetch features/limits via verify
        $verify = wp_remote_get($this->license_server . '/verify', array(
            'headers' => array('Authorization' => 'Bearer ' . $access_token),
            'timeout' => 20
        ));
        if (!is_wp_error($verify)) {
            $vbody = wp_remote_retrieve_body($verify);
            $vdata = json_decode($vbody, true);
            if (isset($vdata['valid']) && $vdata['valid']) {
                update_option('egp_license_data', $vdata);
            }
        }
        
        return true;
    }
    
    /**
     * Deactivate license
     */
    private function deactivate_license() {
        $license_key = get_option('egp_license_key');
        
        if (!$license_key) {
            return true; // Already deactivated
        }
        
        $site_url = get_site_url();
        
        $response = wp_remote_post($this->license_server . '/deactivate', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode(array(
                'licenseKey' => $license_key,
                'domain' => wp_parse_url($site_url, PHP_URL_HOST)
            )),
            'timeout' => 30
        ));
        
        // Even if the server request fails, we should deactivate locally
        delete_option('egp_license_key');
        delete_option('egp_license_status');
        delete_option('egp_license_data');
        delete_option('egp_license_access_token');
        delete_option('egp_license_refresh_token');
        delete_option('egp_license_expires_at');
        
        return true;
    }
    
    /**
     * Check license status
     */
    public function check_license_status() {
        $license_key = get_option('egp_license_key');
        
        if (!$license_key) {
            return true; // No license to check
        }
        
        $site_url = get_site_url();
        
        $access_token = get_option('egp_license_access_token', '');
        if ($access_token) {
            $response = wp_remote_get($this->license_server . '/verify', array(
                'headers' => array('Authorization' => 'Bearer ' . $access_token),
                'timeout' => 20
            ));
        } else {
            return new WP_Error('no_token', __('No license token available', 'elementor-geo-popup'));
        }
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || (isset($data['valid']) && !$data['valid'])) {
            // Attempt token refresh if refresh token exists
            $refresh_token = get_option('egp_license_refresh_token', '');
            if ($refresh_token) {
                $refresh = wp_remote_post($this->license_server . '/refresh', array(
                    'headers' => array('Content-Type' => 'application/json'),
                    'body' => wp_json_encode(array('refreshToken' => $refresh_token)),
                    'timeout' => 20
                ));
                if (!is_wp_error($refresh)) {
                    $rbody = wp_remote_retrieve_body($refresh);
                    $rdata = json_decode($rbody, true);
                    $new_access = $rdata['accessToken'] ?? $rdata['access_token'] ?? '';
                    $new_refresh = $rdata['refreshToken'] ?? $rdata['refresh_token'] ?? '';
                    $new_exp = intval($rdata['expires_at'] ?? 0);
                    if ($new_access && $new_refresh) {
                        update_option('egp_license_access_token', $new_access);
                        update_option('egp_license_refresh_token', $new_refresh);
                        update_option('egp_license_expires_at', $new_exp);
                        // Retry verify
                        $response = wp_remote_get($this->license_server . '/verify', array(
                            'headers' => array('Authorization' => 'Bearer ' . $new_access),
                            'timeout' => 20
                        ));
                        if (!is_wp_error($response)) {
                            $body = wp_remote_retrieve_body($response);
                            $data = json_decode($body, true);
                        }
                    }
                }
            }
            if (!$data || (isset($data['valid']) && !$data['valid'])) {
                update_option('egp_license_status', 'invalid');
                return new WP_Error('license_invalid', __('License is invalid', 'elementor-geo-popup'));
            }
        }
        
        update_option('egp_license_status', 'valid');
        update_option('egp_license_data', $data);
        
        return true;
    }
    
    /**
     * Check if license is valid
     */
    public function is_license_valid() {
        $status = get_option('egp_license_status');
        return $status === 'valid';
    }
    
    /**
     * Display license notices
     */
    public function license_notices() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $license_status = get_option('egp_license_status');
        
        if ($license_status === 'invalid' || $license_status === 'expired') {
            ?>
            <div class="notice notice-error">
                <p>
                    <?php _e('Elementor Geo Popup license is invalid or expired. Please', 'elementor-geo-popup'); ?>
                    <a href="<?php echo admin_url('options-general.php?page=egp-license'); ?>">
                        <?php _e('check your license', 'elementor-geo-popup'); ?>
                    </a>
                    <?php _e('to continue using all features.', 'elementor-geo-popup'); ?>
                </p>
            </div>
            <?php
        } elseif ($license_status === 'inactive') {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php _e('Elementor Geo Popup license is not activated. Please', 'elementor-geo-popup'); ?>
                    <a href="<?php echo admin_url('options-general.php?page=egp-license'); ?>">
                        <?php _e('activate your license', 'elementor-geo-popup'); ?>
                    </a>
                    <?php _e('to access all features.', 'elementor-geo-popup'); ?>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Get license data
     */
    public function get_license_data() {
        return get_option('egp_license_data', array());
    }
}



