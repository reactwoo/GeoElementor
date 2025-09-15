<?php
/**
 * Geo Rules System - Core functionality for page targeting
 * 
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Geo Rules Class
 */
class EGP_Geo_Rules {
    
    private static $instance = null;
    private $post_type = 'geo_rule';
    private $meta_prefix = 'egp_';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Defensive check: ensure required functions are available
        if (function_exists('add_action') && function_exists('wp_create_nonce')) {
            $this->init_hooks();
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register Custom Post Type
        add_action('init', array($this, 'register_post_type'));
        
        // Add meta boxes
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        
        // Frontend targeting
        add_action('wp_head', array($this, 'add_tracking_data'));
        add_action('wp_footer', array($this, 'add_analytics_script'));
        add_action('wp_footer', array($this, 'add_element_geo_filter_script'), 20);
        
        // Elementor integration
        add_action('elementor/editor/before_enqueue_scripts', array($this, 'enqueue_editor_scripts'));
        add_action('elementor/dynamic_tags/register', array($this, 'register_dynamic_tags'));

        // Automatic tracking injection for Elementor elements
        add_action('elementor/frontend/section/before_render', array($this, 'inject_section_tracking'));
        add_action('elementor/frontend/container/before_render', array($this, 'inject_container_tracking'));
        add_action('elementor/frontend/widget/before_render', array($this, 'inject_widget_tracking'));
        
        // AJAX handlers - register with defensive checks
        add_action('wp_ajax_egp_get_geo_rules', array($this, 'ajax_get_geo_rules'));
        add_action('wp_ajax_egp_save_geo_rule', array($this, 'ajax_save_geo_rule'));
        add_action('wp_ajax_nopriv_egp_track_click', array($this, 'ajax_track_click'));
        add_action('wp_ajax_egp_track_click', array($this, 'ajax_track_click'));
        // Views tracking
        add_action('wp_ajax_nopriv_egp_track_view', array($this, 'ajax_track_view'));
        add_action('wp_ajax_egp_track_view', array($this, 'ajax_track_view'));
        // New comprehensive tracking actions
        add_action('wp_ajax_nopriv_egp_track_impression', array($this, 'ajax_track_impression'));
        add_action('wp_ajax_egp_track_impression', array($this, 'ajax_track_impression'));
        add_action('wp_ajax_nopriv_egp_track_form_submit', array($this, 'ajax_track_form_submit'));
        add_action('wp_ajax_egp_track_form_submit', array($this, 'ajax_track_form_submit'));
        add_action('wp_ajax_nopriv_egp_track_form_field_focus', array($this, 'ajax_track_form_field_focus'));
        add_action('wp_ajax_egp_track_form_field_focus', array($this, 'ajax_track_form_field_focus'));
        add_action('wp_ajax_egp_get_target_options', array($this, 'ajax_get_target_options'));
        add_action('wp_ajax_egp_save_elementor_geo_rule', array($this, 'ajax_save_elementor_geo_rule'));
        add_action('wp_ajax_egp_remove_elementor_geo_rule', array($this, 'ajax_remove_elementor_geo_rule'));
        add_action('wp_ajax_egp_get_rule_by_element', array($this, 'ajax_get_rule_by_element'));
        add_action('wp_ajax_egp_get_rule_by_popup', array($this, 'ajax_get_rule_by_popup'));
        add_action('wp_ajax_egp_get_rule_target', array($this, 'ajax_get_rule_target'));
        add_action('wp_ajax_egp_get_countries', array($this, 'ajax_get_countries'));
        // No-public: internal conflict check helper via AJAX if needed later

        // Sync: When an Elementor Popup is saved with geo targeting enabled, create/update a matching Rule
        add_action('save_post_elementor_library', array($this, 'maybe_sync_rule_from_popup_settings'), 20, 3);

        // Cleanup: When a Geo Rule is deleted or trashed, disable geo-targeting on linked popup
        add_action('before_delete_post', array($this, 'maybe_disable_popup_on_rule_delete'));
        add_action('trashed_post', array($this, 'maybe_disable_popup_on_rule_delete'));
        add_action('deleted_post', array($this, 'maybe_disable_popup_on_rule_delete'));
    }
    
    /**
     * Register Custom Post Type
     */
    public function register_post_type() {
        $labels = array(
            'name' => __('Geo Rules', 'elementor-geo-popup'),
            'singular_name' => __('Geo Rule', 'elementor-geo-popup'),
            'menu_name' => __('Geo Rules', 'elementor-geo-popup'),
            'add_new' => __('Add New Rule', 'elementor-geo-popup'),
            'add_new_item' => __('Add New Geo Rule', 'elementor-geo-popup'),
            'edit_item' => __('Edit Geo Rule', 'elementor-geo-popup'),
            'new_item' => __('New Geo Rule', 'elementor-geo-popup'),
            'view_item' => __('View Geo Rule', 'elementor-geo-popup'),
            'search_items' => __('Search Geo Rules', 'elementor-geo-popup'),
            'not_found' => __('No geo rules found', 'elementor-geo-popup'),
            'not_found_in_trash' => __('No geo rules found in trash', 'elementor-geo-popup'),
        );
        
        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false, // Don't show in menu - handled manually
            'capability_type' => 'post',
            'hierarchical' => false,
            'rewrite' => false,
            'supports' => array('title', 'editor'),
            'menu_position' => 30,
        );
        
        register_post_type($this->post_type, $args);
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'egp_geo_targeting',
            __('Geo Targeting Settings', 'elementor-geo-popup'),
            array($this, 'render_targeting_meta_box'),
            $this->post_type,
            'normal',
            'high'
        );
        
        add_meta_box(
            'egp_tracking',
            __('Tracking & Analytics', 'elementor-geo-popup'),
            array($this, 'render_tracking_meta_box'),
            $this->post_type,
            'side',
            'default'
        );
    }
    
    /**
     * Render targeting meta box
     */
    public function render_targeting_meta_box($post) {
        wp_nonce_field('egp_geo_rule', 'egp_geo_rule_nonce');
        
        $target_type = get_post_meta($post->ID, $this->meta_prefix . 'target_type', true);
        $target_id = get_post_meta($post->ID, $this->meta_prefix . 'target_id', true);
        $countries = get_post_meta($post->ID, $this->meta_prefix . 'countries', true);
        $priority = get_post_meta($post->ID, $this->meta_prefix . 'priority', true);
        $active = get_post_meta($post->ID, $this->meta_prefix . 'active', true);
        
        // Debug: Log what's being retrieved
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EGP Debug: Retrieved countries for rule {$post->ID}: " . print_r($countries, true));
        }
        
        if (!is_array($countries)) {
            $countries = array();
        }
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="egp_target_type"><?php _e('Target Type', 'elementor-geo-popup'); ?></label>
                </th>
                <td>
                    <select name="egp_target_type" id="egp_target_type" onchange="egpUpdateTargetOptions()">
                        <option value=""><?php _e('Select Target Type', 'elementor-geo-popup'); ?></option>
                        <option value="page" <?php selected($target_type, 'page'); ?>><?php _e('Page', 'elementor-geo-popup'); ?></option>
                        <option value="popup" <?php selected($target_type, 'popup'); ?>><?php _e('Popup', 'elementor-geo-popup'); ?></option>
                        <option value="section" <?php selected($target_type, 'section'); ?>><?php _e('Section (Pro)', 'elementor-geo-popup'); ?></option>
                        <option value="widget" <?php selected($target_type, 'widget'); ?>><?php _e('Widget (Pro)', 'elementor-geo-popup'); ?></option>
                    </select>
                    <p class="description"><?php _e('What type of content to target', 'elementor-geo-popup'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="egp_target_id"><?php _e('Target Selection', 'elementor-geo-popup'); ?></label>
                </th>
                <td>
                    <div id="egp_target_selection">
                        <p class="description"><?php _e('Select a target type first', 'elementor-geo-popup'); ?></p>
                    </div>
                    <input type="hidden" name="egp_target_id" id="egp_target_id" value="<?php echo esc_attr($target_id); ?>" />
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="egp_countries"><?php _e('Target Countries', 'elementor-geo-popup'); ?></label>
                </th>
                <td>
                    <select name="egp_countries[]" id="egp_countries" multiple="multiple" style="width: 100%; min-height: 120px;">
                        <?php foreach ($this->get_countries_list() as $code => $name): ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php echo in_array($code, $countries) ? 'selected="selected"' : ''; ?>>
                                <?php echo esc_html($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Hold Ctrl/Cmd to select multiple countries', 'elementor-geo-popup'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="egp_priority"><?php _e('Priority', 'elementor-geo-popup'); ?></label>
                </th>
                <td>
                    <input type="number" name="egp_priority" id="egp_priority" value="<?php echo esc_attr($priority); ?>" min="1" max="100" />
                    <p class="description"><?php _e('Higher numbers take precedence (1-100)', 'elementor-geo-popup'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="egp_active"><?php _e('Active', 'elementor-geo-popup'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="egp_active" id="egp_active" value="1" <?php checked($active, '1'); ?> />
                        <?php _e('Enable this geo rule', 'elementor-geo-popup'); ?>
                    </label>
                </td>
            </tr>
        </table>
        
        <script>
        var egpProGranularEnabled = <?php echo apply_filters('egp_enable_element_granularity', $this->is_pro_user()) ? 'true' : 'false'; ?>;
        function egpUpdateTargetOptions() {
            var targetType = document.getElementById('egp_target_type').value;
            var targetSelection = document.getElementById('egp_target_selection');
            var targetIdField = document.getElementById('egp_target_id');
            
            if (!targetType) {
                targetSelection.innerHTML = '<p class="description"><?php _e('Select a target type first', 'elementor-geo-popup'); ?></p>';
                return;
            }
            
            // Show loading
            targetSelection.innerHTML = '<p class="description"><?php _e('Loading options...', 'elementor-geo-popup'); ?></p>';
            
            // Fetch options based on target type
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        egpRenderTargetOptions(targetType, response.data, targetIdField.value);
                    } else {
                        targetSelection.innerHTML = '<p class="description"><?php _e('Error loading options', 'elementor-geo-popup'); ?></p>';
                    }
                }
            };
            xhr.send('action=egp_get_target_options&target_type=' + targetType + '&nonce=<?php echo wp_create_nonce('egp_admin_nonce'); ?>');
        }
        
        function egpRenderTargetOptions(targetType, options, selectedValue) {
            var targetSelection = document.getElementById('egp_target_selection');
            var html = '';
            
            if (targetType === 'page') {
                html = '<select name="egp_target_id_select" id="egp_target_id_select">';
                html += '<option value=""><?php _e('Select a page', 'elementor-geo-popup'); ?></option>';
                html += '<option value="all" ' + (selectedValue === 'all' ? 'selected' : '') + '><?php _e('All Pages', 'elementor-geo-popup'); ?></option>';
                options.forEach(function(option) {
                    html += '<option value="' + option.id + '" ' + (selectedValue == option.id ? 'selected' : '') + '>' + option.title + '</option>';
                });
                html += '</select>';
            } else if (targetType === 'popup') {
                html = '<select name="egp_target_id_select" id="egp_target_id_select">';
                html += '<option value=""><?php _e('Select a popup', 'elementor-geo-popup'); ?></option>';
                html += '<option value="all" ' + (selectedValue === 'all' ? 'selected' : '') + '><?php _e('All Popups', 'elementor-geo-popup'); ?></option>';
                options.forEach(function(option) {
                    html += '<option value="' + option.id + '" ' + (selectedValue == option.id ? 'selected' : '') + '>' + option.title + '</option>';
                });
                html += '</select>';
            } else if (targetType === 'section') {
                // Manual ID input
                var idVal = (selectedValue || '').replace(/^template:/, '').replace(/^#/, '');
                html = '<div class="egp-target-mode" style="display:grid;grid-template-columns:1fr;gap:8px;max-width:720px;">';
                html += '<div>'; 
                html += '<label style="display:block;font-weight:600;margin-bottom:4px;"><?php _e('Target by CSS ID or Elementor ID', 'elementor-geo-popup'); ?></label>';
                html += '<input type="text" id="egp_section_ref" placeholder="my-section-id or elementor data-id" value="' + idVal + '" style="width:100%;max-width:420px;">';
                html += '<div class="description" style="margin-top:4px;"><?php echo esc_js(__('Set in Advanced > CSS ID (no #), or use element data-id.', 'elementor-geo-popup')); ?></div>';
                html += '</div>';
                // Optional template selector if options provided
                html += '<div>';
                if (Array.isArray(options) && options.length){
                    html += '<label style="display:block;font-weight:600;margin-bottom:4px;"><?php _e('Or select a Section/Container template', 'elementor-geo-popup'); ?></label>';
                    html += '<select name="egp_section_template" id="egp_section_template" style="width:100%;max-width:420px;">';
                    html += '<option value="">— <?php _e('Select a template', 'elementor-geo-popup'); ?> —</option>';
                    options.forEach(function(option){
                        var val = 'template:' + option.id;
                        html += '<option value="' + val + '" ' + (selectedValue === val ? 'selected' : '') + '>' + option.title + '</option>';
                    });
                    html += '</select> ';
                    html += '<a href="#" class="button button-small" id="egp_section_edit_tpl" style="vertical-align:middle;"><?php _e('Edit in Elementor', 'elementor-geo-popup'); ?></a>';
                    html += '<div class="description" style="margin-top:4px;"><a href="<?php echo esc_url( admin_url('edit.php?post_type=elementor_library&tabs_group=library&rw_open_new=1') ); ?>" target="_blank"><?php _e('Create new template in Elementor', 'elementor-geo-popup'); ?></a></div>';
                } else {
                    html += '<label style="display:block;font-weight:600;margin-bottom:4px;"><?php _e('Section/Container template', 'elementor-geo-popup'); ?></label>';
                    html += '<div class="description"><?php echo esc_js(__('No templates found.', 'elementor-geo-popup')); ?> ' +
                            '<a href="<?php echo esc_url( admin_url('edit.php?post_type=elementor_library&tabs_group=library&rw_open_new=1') ); ?>" target="_blank"><?php _e('Create new template in Elementor', 'elementor-geo-popup'); ?></a></div>';
                }
                html += '</div>';
                html += '</div>';
            } else if (targetType === 'widget') {
                var idValW = (selectedValue || '').replace(/^template:/, '').replace(/^#/, '');
                html = '<div class="egp-target-mode" style="display:grid;grid-template-columns:1fr;gap:8px;max-width:720px;">';
                html += '<div>';
                html += '<label style="display:block;font-weight:600;margin-bottom:4px;"><?php _e('Target by CSS ID or Elementor ID', 'elementor-geo-popup'); ?></label>';
                html += '<input type="text" id="egp_widget_ref" placeholder="my-widget-id or elementor data-id" value="' + idValW + '" style="width:100%;max-width:420px;">';
                html += '<div class="description" style="margin-top:4px;"><?php echo esc_js(__('Use Advanced > CSS ID (no #) or element data-id.', 'elementor-geo-popup')); ?></div>';
                html += '</div>';
                if (Array.isArray(options) && options.length){
                    html += '<div>';
                    html += '<label style="display:block;font-weight:600;margin-bottom:4px;"><?php _e('Or select a Global Widget/Container template', 'elementor-geo-popup'); ?></label>';
                    html += '<select name="egp_widget_template" id="egp_widget_template" style="width:100%;max-width:420px;">';
                    html += '<option value="">— <?php _e('Select a template', 'elementor-geo-popup'); ?> —</option>';
                    options.forEach(function(option){
                        var val = 'template:' + option.id;
                        html += '<option value="' + val + '" ' + (selectedValue === val ? 'selected' : '') + '>' + option.title + '</option>';
                    });
                    html += '</select> ';
                    html += '<a href="#" class="button button-small" id="egp_widget_edit_tpl" style="vertical-align:middle;"><?php _e('Edit in Elementor', 'elementor-geo-popup'); ?></a>';
                    html += '<div class="description" style="margin-top:4px;"><a href="<?php echo esc_url( admin_url('edit.php?post_type=elementor_library&tabs_group=library&rw_open_new=1') ); ?>" target="_blank"><?php _e('Create new template in Elementor', 'elementor-geo-popup'); ?></a></div>';
                    html += '</div>';
                } else {
                    html += '<div>';
                    html += '<label style="display:block;font-weight:600;margin-bottom:4px;"><?php _e('Global Widget/Container template', 'elementor-geo-popup'); ?></label>';
                    html += '<div class="description"><?php echo esc_js(__('No templates found.', 'elementor-geo-popup')); ?> ' +
                            '<a href="<?php echo esc_url( admin_url('edit.php?post_type=elementor_library&tabs_group=library&rw_open_new=1') ); ?>" target="_blank"><?php _e('Create new template in Elementor', 'elementor-geo-popup'); ?></a></div>';
                    html += '</div>';
                }
                html += '</div>';
            }
            
            targetSelection.innerHTML = html;
            
            // Now add the event listener to the newly created select element
            var selectElement = targetSelection.querySelector('select');
            if (selectElement) {
                selectElement.addEventListener('change', function() { egpUpdateTargetId(this.value); });
            }
            // Section/Widget ID inputs
            var secRef = targetSelection.querySelector('#egp_section_ref');
            var widRef = targetSelection.querySelector('#egp_widget_ref');
            // Edit in Elementor buttons (for template selections)
            var secTpl = targetSelection.querySelector('#egp_section_template');
            var widTpl = targetSelection.querySelector('#egp_widget_template');
            var secEditBtn = targetSelection.querySelector('#egp_section_edit_tpl');
            var widEditBtn = targetSelection.querySelector('#egp_widget_edit_tpl');
            function openTpl(selectEl){
                if (!selectEl) return;
                var v = (selectEl.value || '');
                if (v.indexOf('template:') === 0) {
                    var id = parseInt(v.replace('template:','').replace(/\D+/g,''), 10);
                    if (id > 0) { window.open('<?php echo admin_url('post.php'); ?>?post=' + id + '&action=elementor', '_blank'); }
                }
            }
            if (secEditBtn && secTpl) { secEditBtn.addEventListener('click', function(e){ e.preventDefault(); openTpl(secTpl); }); }
            if (widEditBtn && widTpl) { widEditBtn.addEventListener('click', function(e){ e.preventDefault(); openTpl(widTpl); }); }
            function wireRef(refInput){
                if (!refInput) { return; }
                refInput.addEventListener('input', function(){
                    var val = (this.value || '').replace(/^#/, '');
                    egpUpdateTargetId(val);
                });
                if (refInput.value) { egpUpdateTargetId((refInput.value || '').replace(/^#/, '')); }
            }
            wireRef(secRef);
            wireRef(widRef);
        }
        
        function egpUpdateTargetId(value) {
            var targetIdField = document.getElementById('egp_target_id');
            targetIdField.value = value;
            
            // Debug: Log the update
            if (window.console && console.log) {
                console.log('EGP: Updated target ID to:', value);
                console.log('EGP: Target ID field value is now:', targetIdField.value);
            }
            
            // Update the display to show what was selected, but keep the dropdown for editing
            var targetSelection = document.getElementById('egp_target_selection');
            var currentSelect = targetSelection.querySelector('select');
            if (currentSelect) {
                // Update the select value
                currentSelect.value = value;
                
                // Add a visual indicator of what's selected
                var selectedIndicator = targetSelection.querySelector('.selected-indicator');
                if (!selectedIndicator) {
                    selectedIndicator = document.createElement('p');
                    selectedIndicator.className = 'selected-indicator description';
                    selectedIndicator.style.marginTop = '5px';
                    selectedIndicator.style.fontWeight = 'bold';
                    targetSelection.appendChild(selectedIndicator);
                }
                
                var selectedOption = currentSelect.querySelector('option[value="' + value + '"]');
                if (selectedOption) {
                    selectedIndicator.innerHTML = '<?php _e('Currently selected:', 'elementor-geo-popup'); ?> <span style="color: #0073aa;">' + selectedOption.textContent + '</span>';
                }
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            var targetType = document.getElementById('egp_target_type').value;
            var targetId = document.getElementById('egp_target_id').value;
            
            // Debug logging
            if (window.console && console.log) {
                console.log('EGP DOM Ready - Target Type:', targetType, 'Target ID:', targetId);
            }
            
            // If we have a target type (even without an ID), load options so user can select
            if (targetType) {
                setTimeout(function() {
                    egpUpdateTargetOptions();
                }, 50);
            }
            
            // Add form submit handler to ensure target_id is properly set
            var form = document.querySelector('form#post');
            if (form) {
                form.addEventListener('submit', function(e) {
                    var targetType = document.getElementById('egp_target_type').value;
                    var targetId = document.getElementById('egp_target_id').value;
                    
                    // Debug logging
                    if (window.console && console.log) {
                        console.log('EGP Form Submit - Target Type:', targetType, 'Target ID:', targetId);
                    }
                    
                    // If target type is selected but no target ID, try to get it from the dropdown
                    if (targetType && !targetId) {
                        var targetSelect = document.getElementById('egp_target_id_select');
                        if (targetSelect && targetSelect.value) {
                            document.getElementById('egp_target_id').value = targetSelect.value;
                            if (window.console && console.log) {
                                console.log('EGP Form Submit - Updated target_id to:', targetSelect.value);
                            }
                        } else {
                            // Only prevent if no target is selected in dropdown
                            e.preventDefault();
                            alert('<?php _e('Please select a target before saving.', 'elementor-geo-popup'); ?>');
                            return false;
                        }
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Render tracking meta box
     */
    public function render_tracking_meta_box($post) {
        $tracking_id = get_post_meta($post->ID, $this->meta_prefix . 'tracking_id', true);
        $analytics_enabled = get_post_meta($post->ID, $this->meta_prefix . 'analytics_enabled', true);
        
        if (empty($tracking_id)) {
            $tracking_id = 'geo_' . $post->ID;
        }
        
        ?>
        <p>
            <label for="egp_tracking_id">
                <strong><?php _e('Tracking ID', 'elementor-geo-popup'); ?></strong>
            </label>
            <input type="text" name="egp_tracking_id" id="egp_tracking_id" value="<?php echo esc_attr($tracking_id); ?>" class="widefat" />
            <small><?php _e('Used for analytics and tracking', 'elementor-geo-popup'); ?></small>
        </p>
        
        <p>
            <label>
                <input type="checkbox" name="egp_analytics_enabled" value="1" <?php checked($analytics_enabled, '1'); ?> />
                <?php _e('Enable Analytics Tracking', 'elementor-geo-popup'); ?>
            </label>
        </p>
        
        <?php if (!$this->is_pro_user()): ?>
        <div class="egp-pro-notice">
            <p><strong><?php _e('Pro Features Available:', 'elementor-geo-popup'); ?></strong></p>
            <ul>
                <li><?php _e('Advanced conversion tracking', 'elementor-geo-popup'); ?></li>
                <li><?php _e('A/B testing capabilities', 'elementor-geo-popup'); ?></li>
                <li><?php _e('Export analytics data', 'elementor-geo-popup'); ?></li>
            </ul>
        </div>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Save meta boxes
     */
    public function save_meta_boxes($post_id) {
        // Security checks
        if (!isset($_POST['egp_geo_rule_nonce']) || !wp_verify_nonce($_POST['egp_geo_rule_nonce'], 'egp_geo_rule')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save targeting data with conflict check against Groups
        if (isset($_POST['egp_target_type'])) {
            $target_type = sanitize_text_field($_POST['egp_target_type']);
            update_post_meta($post_id, $this->meta_prefix . 'target_type', $target_type);
        } else {
            $target_type = get_post_meta($post_id, $this->meta_prefix . 'target_type', true);
        }
        
        // Determine target_id from hidden field, with fallback to the visible select
        $posted_target_id = null;
        if (isset($_POST['egp_target_id']) && $_POST['egp_target_id'] !== '') {
            $posted_target_id = sanitize_text_field($_POST['egp_target_id']);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("EGP Debug: Using egp_target_id: {$posted_target_id}");
            }
        } elseif (isset($_POST['egp_target_id_select']) && $_POST['egp_target_id_select'] !== '') {
            $posted_target_id = sanitize_text_field($_POST['egp_target_id_select']);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("EGP Debug: Using egp_target_id_select: {$posted_target_id}");
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("EGP Debug: No target_id found in POST data");
            }
        }
        
        if ($posted_target_id !== null) {
            $target_id = $posted_target_id;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("EGP Debug: Saving target_id: {$target_id} for target_type: {$target_type}");
            }
            // Only check numeric IDs for page/popup types
            if (in_array($target_type, array('page','popup'), true) && ctype_digit((string) $target_id)) {
                if ($this->group_conflict_exists($target_type, intval($target_id))) {
                    // Save the selection but mark rule inactive to surface the conflict clearly
                    update_post_meta($post_id, $this->meta_prefix . 'target_id', $target_id);
                    update_post_meta($post_id, $this->meta_prefix . 'active', '0');
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("EGP Debug: Group conflict detected, rule marked inactive");
                    }
                } else {
                    update_post_meta($post_id, $this->meta_prefix . 'target_id', $target_id);
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("EGP Debug: Target ID saved successfully");
                    }
                }
            } else {
                // Non-numeric or other types: just persist
                update_post_meta($post_id, $this->meta_prefix . 'target_id', $target_id);
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("EGP Debug: Non-numeric target ID saved");
                }
            }
        }
        
        if (isset($_POST['egp_countries'])) {
            $countries = array_map('sanitize_text_field', $_POST['egp_countries']);
            // Debug: Log what's being saved
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("EGP Debug: Saving countries for rule {$post_id}: " . print_r($countries, true));
            }
            update_post_meta($post_id, $this->meta_prefix . 'countries', $countries);
        }
        
        if (isset($_POST['egp_priority'])) {
            update_post_meta($post_id, $this->meta_prefix . 'priority', intval($_POST['egp_priority']));
        }
        
        if (isset($_POST['egp_active'])) {
            update_post_meta($post_id, $this->meta_prefix . 'active', '1');
        } else {
            update_post_meta($post_id, $this->meta_prefix . 'active', '0');
        }
        
        // Save tracking data
        if (isset($_POST['egp_tracking_id'])) {
            update_post_meta($post_id, $this->meta_prefix . 'tracking_id', sanitize_text_field($_POST['egp_tracking_id']));
        }
        
        if (isset($_POST['egp_analytics_enabled'])) {
            update_post_meta($post_id, $this->meta_prefix . 'analytics_enabled', '1');
        } else {
            update_post_meta($post_id, $this->meta_prefix . 'analytics_enabled', '0');
        }
        
        // Initialize clicks count if not set
        if (!get_post_meta($post_id, $this->meta_prefix . 'clicks', true)) {
            update_post_meta($post_id, $this->meta_prefix . 'clicks', 0);
        }
        
        // Sync with Elementor popup settings if this is a popup rule
        if ($target_type === 'popup' && $posted_target_id !== null) {
            $popup_id = intval($posted_target_id);
            // Only sync if the target_id matches the popup_id
            if ($popup_id > 0) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("EGP Debug: Attempting to sync rule {$post_id} to popup {$popup_id}");
                }
                $this->sync_rule_to_popup_settings($post_id, $popup_id);
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("EGP Debug: Invalid popup_id for sync: {$posted_target_id}");
                }
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("EGP Debug: Not syncing - target_type: {$target_type}, posted_target_id: " . var_export($posted_target_id, true));
            }
        }
    }
    
    /**
     * Track clicks for a geo rule
     */
    public function track_click($rule_id) {
        if (!$rule_id) {
            return;
        }
        
        $current_clicks = get_post_meta($rule_id, $this->meta_prefix . 'clicks', true);
        $new_clicks = intval($current_clicks) + 1;
        update_post_meta($rule_id, $this->meta_prefix . 'clicks', $new_clicks);
        
        // Log click for analytics
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EGP Geo Rule: Click tracked for rule ID {$rule_id}, total clicks: {$new_clicks}");
        }
    }

    /**
     * Track views for a geo rule
     */
    public function track_view($rule_id) {
        if (!$rule_id) {
            return;
        }
        $current_views = get_post_meta($rule_id, $this->meta_prefix . 'views', true);
        $new_views = intval($current_views) + 1;
        update_post_meta($rule_id, $this->meta_prefix . 'views', $new_views);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EGP Geo Rule: View tracked for rule ID {$rule_id}, total views: {$new_views}");
        }
    }

    /**
     * Track impressions for a geo rule
     */
    public function track_impression($rule_id, $element_type = 'unknown') {
        if (!$rule_id) {
            return;
        }

        $current_impressions = get_post_meta($rule_id, $this->meta_prefix . 'impressions', true);
        $new_impressions = intval($current_impressions) + 1;
        update_post_meta($rule_id, $this->meta_prefix . 'impressions', $new_impressions);

        // Track element type specific impressions
        if ($element_type && $element_type !== 'unknown') {
            $element_key = $this->meta_prefix . 'impressions_' . $element_type;
            $current_element_impressions = get_post_meta($rule_id, $element_key, true);
            $new_element_impressions = intval($current_element_impressions) + 1;
            update_post_meta($rule_id, $element_key, $new_element_impressions);
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EGP Geo Rule: Impression tracked for rule ID {$rule_id}, element type: {$element_type}, total impressions: {$new_impressions}");
        }
    }

    /**
     * Track form submissions
     */
    public function track_form_submit($rule_id, $form_id, $field_count, $data = array()) {
        if (!$rule_id) {
            return;
        }

        // Track total form submissions
        $current_submissions = get_post_meta($rule_id, $this->meta_prefix . 'form_submissions', true);
        $new_submissions = intval($current_submissions) + 1;
        update_post_meta($rule_id, $this->meta_prefix . 'form_submissions', $new_submissions);

        // Track form-specific data
        if ($form_id) {
            $form_key = $this->meta_prefix . 'form_' . $form_id . '_submissions';
            $current_form_submissions = get_post_meta($rule_id, $form_key, true);
            $new_form_submissions = intval($current_form_submissions) + 1;
            update_post_meta($rule_id, $form_key, $new_form_submissions);
        }

        // Store form metadata
        if ($field_count > 0) {
            update_post_meta($rule_id, $this->meta_prefix . 'form_field_count', $field_count);
        }

        if (isset($data['has_required']) && $data['has_required']) {
            $current_required = get_post_meta($rule_id, $this->meta_prefix . 'form_has_required', true);
            if (!$current_required) {
                update_post_meta($rule_id, $this->meta_prefix . 'form_has_required', '1');
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EGP Geo Rule: Form submission tracked for rule ID {$rule_id}, form: {$form_id}, total submissions: {$new_submissions}");
        }
    }

    /**
     * Track form field interactions
     */
    public function track_form_field_interaction($rule_id, $field_name, $field_type) {
        if (!$rule_id || !$field_name) {
            return;
        }

        // Track total field interactions
        $current_interactions = get_post_meta($rule_id, $this->meta_prefix . 'field_interactions', true);
        $new_interactions = intval($current_interactions) + 1;
        update_post_meta($rule_id, $this->meta_prefix . 'field_interactions', $new_interactions);

        // Track field-specific interactions
        if ($field_name) {
            $field_key = $this->meta_prefix . 'field_' . sanitize_key($field_name) . '_focus';
            $current_field_focus = get_post_meta($rule_id, $field_key, true);
            $new_field_focus = intval($current_field_focus) + 1;
            update_post_meta($rule_id, $field_key, $new_field_focus);
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EGP Geo Rule: Field interaction tracked for rule ID {$rule_id}, field: {$field_name}, type: {$field_type}, total interactions: {$new_interactions}");
        }
    }
    
    /**
     * Inject tracking attributes into sections with geo targeting
     */
    public function inject_section_tracking($section) {
        $this->inject_element_tracking($section, 'section');
    }

    /**
     * Inject tracking attributes into containers with geo targeting
     */
    public function inject_container_tracking($container) {
        $this->inject_element_tracking($container, 'container');
    }

    /**
     * Inject tracking attributes into widgets with geo targeting
     */
    public function inject_widget_tracking($widget) {
        $this->inject_element_tracking($widget, 'widget');
    }

    /**
     * Generic method to inject tracking into any Elementor element
     */
    private function inject_element_tracking($element, $element_type) {
        // Skip if not a valid element
        if (!$element || !method_exists($element, 'get_settings')) {
            return;
        }

        $settings = $element->get_settings();

        // Check if this element has geo targeting enabled
        $has_geo_targeting = false;

        // Check different ways geo targeting might be enabled
        if (isset($settings['geo_targeting_enabled']) && $settings['geo_targeting_enabled'] === 'yes') {
            $has_geo_targeting = true;
        } elseif (isset($settings['egp_enable_geo_targeting']) && $settings['egp_enable_geo_targeting'] === 'yes') {
            $has_geo_targeting = true;
        }

        if (!$has_geo_targeting) {
            return;
        }

        // Get or create rule for this element
        $rule_id = $this->get_or_create_element_rule($element, $settings, $element_type);
        if (!$rule_id) {
            return;
        }

        // Get existing attributes
        $existing_attributes = $element->get_render_attribute_string('wrapper') ?: '';

        // Add tracking attributes
        $tracking_attributes = ' data-rule-id="' . esc_attr($rule_id) . '"';
        $tracking_attributes .= ' data-element-type="' . esc_attr($element_type) . '"';

        // Add element-specific tracking
        if ($element_type === 'section' || $element_type === 'container') {
            $tracking_attributes .= ' data-track-impression="true"';
        } elseif ($element_type === 'widget') {
            $tracking_attributes .= ' onclick="egpTrackClick(' . esc_attr($rule_id) . ', \'' . esc_attr($element_type) . '\')"';
        }

        // Inject the tracking attributes into the wrapper
        if (method_exists($element, 'add_render_attribute')) {
            $element->add_render_attribute('_wrapper', 'data-rule-id', $rule_id);
            $element->add_render_attribute('_wrapper', 'data-element-type', $element_type);

            if ($element_type === 'section' || $element_type === 'container') {
                $element->add_render_attribute('_wrapper', 'data-track-impression', 'true');
            }
        }
    }

    /**
     * Get or create a rule for an Elementor element
     */
    private function get_or_create_element_rule($element, $settings, $element_type) {
        $element_id = $element->get_id();

        // Check if a rule already exists for this element
        $existing_rules = get_posts(array(
            'post_type' => 'geo_rule',
            'meta_query' => array(
                array(
                    'key' => 'egp_element_id',
                    'value' => $element_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'egp_element_type',
                    'value' => $element_type,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        ));

        if (!empty($existing_rules)) {
            return $existing_rules[0]->ID;
        }

        // Create a new rule for this element
        $element_title = $this->get_element_title($element, $element_type);

        $rule_data = array(
            'post_title' => $element_title,
            'post_type' => 'geo_rule',
            'post_status' => 'publish',
            'meta_input' => array(
                'egp_target_type' => $element_type,
                'egp_element_id' => $element_id,
                'egp_element_type' => $element_type,
                'egp_countries' => isset($settings['target_countries']) ? $settings['target_countries'] : array(),
                'egp_active' => '1',
                'egp_clicks' => 0,
                'egp_views' => 0,
                'egp_impressions' => 0
            )
        );

        $rule_id = wp_insert_post($rule_data);

        if ($rule_id && !is_wp_error($rule_id)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("EGP: Created rule ID {$rule_id} for {$element_type} {$element_id}");
            }
            return $rule_id;
        }

        return 0;
    }

    /**
     * Get a descriptive title for an Elementor element
     */
    private function get_element_title($element, $element_type) {
        $element_id = $element->get_id();

        switch ($element_type) {
            case 'section':
                return "Section ID: {$element_id}";
            case 'container':
                return "Container ID: {$element_id}";
            case 'widget':
                $widget_name = $element->get_name();
                return "Widget: " . ucfirst(str_replace('egp_', '', $widget_name)) . " (ID: {$element_id})";
            default:
                return "{$element_type} ID: {$element_id}";
        }
    }

    /**
     * Add tracking data to head
     */
    public function add_tracking_data() {
        if (is_admin()) {
            return;
        }

        // Emergency disable option - check if frontend tracking is disabled
        if (get_option('egp_disable_frontend_tracking', false)) {
            return;
        }

        // Defensive check: ensure required functions are available
        if (!function_exists('wp_create_nonce') || !function_exists('admin_url')) {
            return;
        }

        // Add data layer for tracking
        echo '<script>window.dataLayer = window.dataLayer || [];</script>';

        // Add comprehensive tracking JavaScript - simplified to prevent crashes
        echo '<script>
        // Global tracking system - simplified version
        window.EGP_Tracking = window.EGP_Tracking || {
            trackedImpressions: new Set(),
            trackedClicks: new Set(),
            formInteractions: new Map(),

            track: function(action, ruleId, data) {
                if (!ruleId) return;

                // Use a generic nonce for all tracking actions to avoid dynamic nonce issues
                var nonce = "' . wp_create_nonce('egp_tracking_nonce') . '";

                var payload = {
                    action: "egp_track_" + action,
                    rule_id: ruleId,
                    nonce: nonce,
                    data: JSON.stringify(data || {})
                };

                // Use XMLHttpRequest instead of fetch for better compatibility
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "' . admin_url('admin-ajax.php') . '", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                var response = JSON.parse(xhr.responseText);
                                if (window.console && console.log) {
                                    console.log("EGP: " + action + " tracked for rule", ruleId, response);
                                }
                            } catch (e) {
                                console.error("EGP: JSON parse error", e);
                            }
                        } else {
                            console.error("EGP tracking error: HTTP " + xhr.status);
                        }
                    }
                };
                xhr.send(new URLSearchParams(payload).toString());

                // Google Analytics integration
                if (window.dataLayer) {
                    window.dataLayer.push({
                        "event": "egp_" + action,
                        "rule_id": ruleId,
                        "timestamp": new Date().toISOString()
                    });
                }
            }
        };

        // Click tracking function
        function egpTrackClick(ruleId, elementType = "widget") {
            if (!ruleId || window.EGP_Tracking.trackedClicks.has(ruleId)) return;

            window.EGP_Tracking.trackedClicks.add(ruleId);
            window.EGP_Tracking.track("click", ruleId, {
                element_type: elementType,
                timestamp: Date.now()
            });
        }

        // View/Impression tracking function
        function egpTrackImpression(ruleId, elementType = "widget") {
            if (!ruleId || window.EGP_Tracking.trackedImpressions.has(ruleId)) return;

            window.EGP_Tracking.trackedImpressions.add(ruleId);
            window.EGP_Tracking.track("impression", ruleId, {
                element_type: elementType,
                timestamp: Date.now()
            });
        }

        // Form interaction tracking
        function egpTrackFormInteraction(formId, action, data = {}) {
            const ruleId = data.ruleId || formId;
            window.EGP_Tracking.track("form_" + action, ruleId, {
                form_id: formId,
                ...data
            });
        }

        // Legacy view tracking (backward compatibility)
        function egpTrackView(ruleId) {
            egpTrackImpression(ruleId, "legacy");
        }

        // Intersection Observer for impression tracking
        if ("IntersectionObserver" in window) {
            window.EGP_Observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const ruleId = entry.target.dataset.ruleId;
                        const elementType = entry.target.dataset.elementType || "section";
                        if (ruleId) {
                            egpTrackImpression(ruleId, elementType);
                        }
                    }
                });
            }, {
                threshold: 0.1, // 10% visibility
                rootMargin: "0px 0px -50px 0px" // Trigger slightly before fully visible
            });
        }

        // Simplified form tracking setup to prevent crashes
        document.addEventListener("DOMContentLoaded", function() {
            // Track form submissions (simplified)
            var forms = document.querySelectorAll("form[data-rule-id]");
            for (var i = 0; i < forms.length; i++) {
                forms[i].addEventListener("submit", function(e) {
                    var form = e.target;
                    var ruleId = form.getAttribute("data-rule-id");
                    if (ruleId) {
                        var fieldCount = form.querySelectorAll("input, select, textarea").length;
                        egpTrackFormInteraction(ruleId, "submit", {
                            ruleId: ruleId,
                            form_id: form.id || form.getAttribute("data-form-id") || "unknown",
                            field_count: fieldCount,
                            has_required: form.querySelectorAll("[required]").length > 0
                        });
                    }
                });
            }

            // Observe elements for impression tracking (simplified)
            var elements = document.querySelectorAll("[data-rule-id][data-track-impression]");
            for (var j = 0; j < elements.length; j++) {
                if (window.EGP_Observer) {
                    window.EGP_Observer.observe(elements[j]);
                }
            }
        });
        </script>';
    }
    
    /**
     * Add analytics script to footer
     */
    public function add_analytics_script() {
        if (is_admin()) {
            return;
        }
        
        $current_page_id = get_queried_object_id();
        $geo_rules = $this->get_matching_rules($current_page_id);
        
        if (empty($geo_rules)) {
            // Record untracked hit only if no active rule exists for this visitor country at all
            $visitor_country = $this->get_user_country();
            if (!$this->country_is_tracked($visitor_country)) {
                $this->increment_untracked_country($visitor_country);
            }
            return;
        }
        
        // Basic tracking (Free tier)
        echo '<script>';
        echo 'if (typeof gtag !== "undefined") {';
        foreach ($geo_rules as $rule) {
            echo 'gtag("event", "geo_rule_viewed", {';
            echo '"rule_id": "' . esc_js($rule['tracking_id']) . '",';
            echo '"rule_type": "' . esc_js($rule['target_type']) . '",';
            echo '"countries": ' . wp_json_encode($rule['countries']) . '';
            echo '});';
        }
        echo '}';
        echo '</script>';
        
        // Pro features (if available)
        if ($this->is_pro_user()) {
            $this->add_pro_tracking($geo_rules);
        }

        // Popup geo filtering handled by JS guard in geo-detect to avoid API conflicts
    }

    /**
     * Increment untracked country counter (when no rules matched the request)
     */
    private function increment_untracked_country($country) {
        $code = strtoupper(trim((string) $country));
        if (!$code || strlen($code) !== 2) { return; }
        $counts = get_option('egp_untracked_country_counts', array());
        if (!is_array($counts)) { $counts = array(); }
        $counts[$code] = isset($counts[$code]) ? intval($counts[$code]) + 1 : 1;
        update_option('egp_untracked_country_counts', $counts, false);
    }

    /**
     * Determine if any active rule (of any type) targets the given country
     */
    private function country_is_tracked($country) {
        $code = strtoupper(trim((string) $country));
        if (!$code || strlen($code) !== 2) { return false; }
        $rules = get_posts(array(
            'post_type' => $this->post_type,
            'post_status' => 'publish',
            'meta_query' => array(
                array('key' => $this->meta_prefix . 'active', 'value' => '1', 'compare' => '=')
            ),
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        if (empty($rules)) { return false; }
        foreach ($rules as $rid) {
            $countries = get_post_meta($rid, $this->meta_prefix . 'countries', true);
            if (is_array($countries) && in_array($code, array_map('strtoupper', $countries), true)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Add Pro tracking features
     */
    private function add_pro_tracking($geo_rules) {
        // Advanced conversion tracking
        echo '<script>';
        echo 'document.addEventListener("DOMContentLoaded", function() {';
        echo 'var forms = document.querySelectorAll("form");';
        echo 'forms.forEach(function(form) {';
        echo 'form.addEventListener("submit", function() {';
        foreach ($geo_rules as $rule) {
            echo 'if (typeof gtag !== "undefined") {';
            echo 'gtag("event", "geo_rule_conversion", {';
            echo '"rule_id": "' . esc_js($rule['tracking_id']) . '",';
            echo '"rule_type": "' . esc_js($rule['target_type']) . '",';
            echo '"event_category": "conversion"';
            echo '});';
            echo '}';
        }
        echo '});';
        echo '});';
        echo '});';
        echo '</script>';
    }
    
    /**
     * Get matching rules for current page
     */
    private function get_matching_rules($page_id) {
        $user_country = $this->get_user_country();
        
        $args = array(
            'post_type' => $this->post_type,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => $this->meta_prefix . 'active',
                    'value' => '1',
                    'compare' => '='
                ),
                array(
                    'key' => $this->meta_prefix . 'target_id',
                    'value' => $page_id,
                    'compare' => '='
                )
            ),
            'orderby' => 'meta_value_num',
            'meta_key' => $this->meta_prefix . 'priority',
            'order' => 'DESC'
        );
        
        $rules = get_posts($args);
        $matching_rules = array();
        
        foreach ($rules as $rule) {
            $countries = get_post_meta($rule->ID, $this->meta_prefix . 'countries', true);
            
            if (in_array($user_country, $countries)) {
                $matching_rules[] = array(
                    'id' => $rule->ID,
                    'title' => $rule->post_title,
                    'target_type' => get_post_meta($rule->ID, $this->meta_prefix . 'target_type', true),
                    'target_id' => get_post_meta($rule->ID, $this->meta_prefix . 'target_id', true),
                    'countries' => $countries,
                    'tracking_id' => get_post_meta($rule->ID, $this->meta_prefix . 'tracking_id', true),
                    'analytics_enabled' => get_post_meta($rule->ID, $this->meta_prefix . 'analytics_enabled', true)
                );
            }
        }
        
        return $matching_rules;
    }

    /**
     * Hide Sections/Containers/Widgets on the frontend when visitor country is not targeted by rules.
     */
    public function add_element_geo_filter_script() {
        if (is_admin()) { return; }
        $user_country = strtoupper($this->get_user_country());
        if (!$user_country) { return; }

        // Fetch active section/widget rules (manual targeting)
        $rules = get_posts(array(
            'post_type' => $this->post_type,
            'post_status' => 'publish',
            'meta_query' => array(
                array('key' => $this->meta_prefix . 'active', 'value' => '1', 'compare' => '=')
            ),
            'posts_per_page' => -1
        ));

        $targets = array();
        foreach ($rules as $rule) {
            $type = get_post_meta($rule->ID, $this->meta_prefix . 'target_type', true);
            if ($type !== 'section' && $type !== 'widget') { continue; }
            $target_id = trim((string) get_post_meta($rule->ID, $this->meta_prefix . 'target_id', true));
            if ($target_id === '' || strpos($target_id, 'template:') === 0) { continue; }
            $countries = get_post_meta($rule->ID, $this->meta_prefix . 'countries', true);
            if (!is_array($countries) || empty($countries)) { continue; }
            $countries = array_values(array_unique(array_map('strtoupper', $countries)));

            // Heuristic: alphanumeric with dashes/underscores => CSS ID; otherwise treat as Elementor data-id
            $is_css_id = (bool) preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $target_id);
            $targets[] = array(
                'ref' => $target_id,
                'refType' => $is_css_id ? 'css' : 'element',
                'countries' => $countries,
            );
        }

        if (empty($targets)) { return; }

        echo '<script>';
        echo 'document.addEventListener("DOMContentLoaded",function(){';
        echo 'var userCountry=' . wp_json_encode($user_country) . ';';
        echo 'var targets=' . wp_json_encode($targets) . ';';
        echo 'var hidden=new Set();';
        echo 'targets.forEach(function(t){var allow=(t.countries||[]).map(function(c){return (c||"").toUpperCase()});if(allow.indexOf(userCountry)===-1){try{var el=null;if(t.refType==="css"){el=document.getElementById(t.ref);}else{el=document.querySelector("[data-id=\""+t.ref+"\"]");}if(el && !hidden.has(t.ref)){el.style.display="none";el.classList.add("egp-hidden");hidden.add(t.ref);}}catch(e){}}});';
        echo '});';
        echo '</script>';
    }
    
    /**
     * Get user country
     */
    private function get_user_country() {
        // Use existing geo detection if available
        if (class_exists('EGP_Geo_Detect')) {
            $geo_detect = EGP_Geo_Detect::get_instance();
            return $geo_detect->get_visitor_country();
        }
        
        // Fallback to basic detection
        $country = get_option('egp_user_country', 'US');
        return $country;
    }
    
    /**
     * Check if user has Pro access
     */
    private function is_pro_user() {
        return current_user_can('manage_woocommerce') || apply_filters('egp_is_pro_user', false);
    }
    
    /**
     * Get countries list
     */
    private function get_countries_list() {
        // Load full ISO-3166 list from bundled data file if available
        $json_path = plugin_dir_path(__FILE__) . '../assets/data/countries.json';
        $json_path = realpath($json_path);
        if ($json_path && file_exists($json_path)) {
            $contents = file_get_contents($json_path);
            $decoded = json_decode($contents, true);
            if (is_array($decoded) && !empty($decoded)) {
                // Convert from array of objects to associative array
                $countries = array();
                foreach ($decoded as $country) {
                    if (isset($country['code']) && isset($country['name'])) {
                        $countries[$country['code']] = $country['name'];
                    }
                }
                if (!empty($countries)) {
                    return $countries;
                }
            }
        }

        // Fallback comprehensive list
        return array(
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'CH' => 'Switzerland',
            'AT' => 'Austria',
            'IE' => 'Ireland',
            'NZ' => 'New Zealand',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'CN' => 'China',
            'IN' => 'India',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'CO' => 'Colombia',
            'PE' => 'Peru',
            'VE' => 'Venezuela',
            'ZA' => 'South Africa',
            'EG' => 'Egypt',
            'NG' => 'Nigeria',
            'KE' => 'Kenya',
            'MA' => 'Morocco',
            'SA' => 'Saudi Arabia',
            'AE' => 'United Arab Emirates',
            'IL' => 'Israel',
            'TR' => 'Turkey',
            'RU' => 'Russia',
            'PL' => 'Poland',
            'CZ' => 'Czech Republic',
            'HU' => 'Hungary',
            'RO' => 'Romania',
            'BG' => 'Bulgaria',
            'HR' => 'Croatia',
            'SI' => 'Slovenia',
            'SK' => 'Slovakia',
            'LT' => 'Lithuania',
            'LV' => 'Latvia',
            'EE' => 'Estonia',
            'MT' => 'Malta',
            'CY' => 'Cyprus',
            'GR' => 'Greece',
            'PT' => 'Portugal'
        );
    }

    /**
     * AJAX: Provide full countries list for editor UIs
     */
    public function ajax_get_countries() {
        check_ajax_referer('egp_admin_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        $countries = $this->get_countries_list();
        asort($countries);
        wp_send_json_success($countries);
    }
    
    /**
     * AJAX: Get geo rules
     */
    public function ajax_get_geo_rules() {
        check_ajax_referer('egp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $args = array(
            'post_type' => $this->post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        $rules = get_posts($args);
        $formatted_rules = array();
        
        foreach ($rules as $rule) {
            $formatted_rules[] = array(
                'id' => $rule->ID,
                'title' => $rule->post_title,
                'target_type' => get_post_meta($rule->ID, $this->meta_prefix . 'target_type', true),
                'target_id' => get_post_meta($rule->ID, $this->meta_prefix . 'target_id', true),
                'countries' => get_post_meta($rule->ID, $this->meta_prefix . 'countries', true),
                'active' => get_post_meta($rule->ID, $this->meta_prefix . 'active', true),
                'priority' => get_post_meta($rule->ID, $this->meta_prefix . 'priority', true),
                'tracking_id' => get_post_meta($rule->ID, $this->meta_prefix . 'tracking_id', true)
            );
        }
        
        wp_send_json_success($formatted_rules);
    }
    
    /**
     * AJAX: Save geo rule
     */
    public function ajax_save_geo_rule() {
        check_ajax_referer('egp_admin_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        error_log('[EGP Debug] ajax_save_geo_rule called with: ' . print_r($_POST, true));

        $target_type = sanitize_text_field($_POST['target_type'] ?? '');
        $target_id = sanitize_text_field($_POST['target_id'] ?? '');
        $element_ref_id = sanitize_text_field($_POST['element_ref_id'] ?? '');
        $countries = isset($_POST['countries']) ? array_map('sanitize_text_field', $_POST['countries']) : array();
        $priority = intval($_POST['priority'] ?? 50);
        $active = !empty($_POST['active']);
        $title = sanitize_text_field($_POST['title'] ?? '');
        $element_type = sanitize_text_field($_POST['element_type'] ?? '');
        $tracking_id = sanitize_text_field($_POST['tracking_id'] ?? '');

        error_log('[EGP Debug] Processed data: target_type=' . $target_type . ', target_id=' . $target_id . ', countries=' . implode(',', $countries));

        $result = $this->save_or_update_rule($target_type, $target_id, $countries, $priority, $active, 'admin_rule', $title, $element_type, $tracking_id);
        
        if ($result['success']) {
            error_log('[EGP Debug] Rule saved successfully: ' . $result['rule_id']);
            wp_send_json_success($result);
        } else {
            error_log('[EGP Debug] Rule save failed: ' . $result['error']);
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * AJAX: Track click for a geo rule
     */
    public function ajax_track_click() {
        // Use generic nonce for tracking to avoid dynamic nonce issues
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'egp_tracking_nonce') &&
            !wp_verify_nonce($_POST['nonce'] ?? '', 'egp_track_click_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
        if (!$rule_id) {
            $popup_id = isset($_POST['popup_id']) ? intval($_POST['popup_id']) : 0;
            if ($popup_id > 0) {
                $rule = $this->get_rule_by_target('popup', (string) $popup_id);
                if ($rule) { $rule_id = intval($rule->ID); }
            }
        }
        if (!$rule_id) {
            wp_send_json_error(__('Invalid rule ID', 'elementor-geo-popup'));
        }
        $this->track_click($rule_id);
        
        wp_send_json_success();
    }

    /**
     * AJAX: Track view for a geo rule
     */
    public function ajax_track_view() {
        // Use generic nonce for tracking to avoid dynamic nonce issues
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'egp_tracking_nonce') &&
            !wp_verify_nonce($_POST['nonce'] ?? '', 'egp_track_view_nonce')) {
            wp_send_json_error('Security check failed');
        }
        $rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
        if (!$rule_id) {
            // Allow fallback by popup_id -> resolve rule
            $popup_id = isset($_POST['popup_id']) ? intval($_POST['popup_id']) : 0;
            if ($popup_id > 0) {
                $rule = $this->get_rule_by_target('popup', (string) $popup_id);
                if ($rule) { $rule_id = intval($rule->ID); }
            }
        }
        if (!$rule_id) {
            wp_send_json_error(__('Invalid rule ID', 'elementor-geo-popup'));
        }
        $this->track_view($rule_id);
        wp_send_json_success();
    }

    /**
     * AJAX: Track impression for a geo rule
     */
    public function ajax_track_impression() {
        // Use generic nonce for tracking to avoid dynamic nonce issues
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'egp_tracking_nonce') &&
            !wp_verify_nonce($_POST['nonce'] ?? '', 'egp_track_impression_nonce')) {
            wp_send_json_error('Security check failed');
        }
        $rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
        if (!$rule_id) {
            wp_send_json_error(__('Invalid rule ID', 'elementor-geo-popup'));
        }

        $data = isset($_POST['data']) ? json_decode(stripslashes($_POST['data']), true) : array();
        $element_type = isset($data['element_type']) ? sanitize_text_field($data['element_type']) : 'unknown';

        $this->track_impression($rule_id, $element_type);
        wp_send_json_success(array('tracked' => true, 'element_type' => $element_type));
    }

    /**
     * AJAX: Track form submission
     */
    public function ajax_track_form_submit() {
        // Use generic nonce for tracking to avoid dynamic nonce issues
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'egp_tracking_nonce') &&
            !wp_verify_nonce($_POST['nonce'] ?? '', 'egp_track_form_submit_nonce')) {
            wp_send_json_error('Security check failed');
        }
        $rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
        if (!$rule_id) {
            wp_send_json_error(__('Invalid rule ID', 'elementor-geo-popup'));
        }

        $data = isset($_POST['data']) ? json_decode(stripslashes($_POST['data']), true) : array();
        $form_id = isset($data['form_id']) ? sanitize_text_field($data['form_id']) : '';
        $field_count = isset($data['field_count']) ? intval($data['field_count']) : 0;

        $this->track_form_submit($rule_id, $form_id, $field_count, $data);
        wp_send_json_success(array('tracked' => true, 'form_id' => $form_id));
    }

    /**
     * AJAX: Track form field focus
     */
    public function ajax_track_form_field_focus() {
        // Use generic nonce for tracking to avoid dynamic nonce issues
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'egp_tracking_nonce') &&
            !wp_verify_nonce($_POST['nonce'] ?? '', 'egp_track_form_field_focus_nonce')) {
            wp_send_json_error('Security check failed');
        }
        $rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
        if (!$rule_id) {
            wp_send_json_error(__('Invalid rule ID', 'elementor-geo-popup'));
        }

        $data = isset($_POST['data']) ? json_decode(stripslashes($_POST['data']), true) : array();
        $field_name = isset($data['field_name']) ? sanitize_text_field($data['field_name']) : '';
        $field_type = isset($data['field_type']) ? sanitize_text_field($data['field_type']) : '';

        $this->track_form_field_interaction($rule_id, $field_name, $field_type);
        wp_send_json_success(array('tracked' => true, 'field_name' => $field_name));
    }

    /**
     * AJAX: Get target options for a specific target type
     */
    public function ajax_get_target_options() {
        check_ajax_referer('egp_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }

        $target_type = sanitize_text_field($_POST['target_type']);
        $options = array();

        if ($target_type === 'page') {
            $pages = get_pages(array('sort_column' => 'post_title', 'sort_order' => 'asc'));
            foreach ($pages as $page) {
                $options[] = array('id' => $page->ID, 'title' => $page->post_title);
            }
        } elseif ($target_type === 'popup') {
            // Support multiple popup post types via filter; default to Elementor and a generic 'popup'
            $popup_post_types = apply_filters('egp_popup_post_types', array('elementor_library', 'popup'));
            $seen_ids = array();
            foreach ($popup_post_types as $ppt) {
                $args = array(
                    'post_type' => $ppt,
                    'post_status' => array('publish','draft','private','inherit','future','pending'),
                    'posts_per_page' => -1,
                    'orderby' => 'title',
                    'order' => 'asc',
                    'no_found_rows' => true,
                    'cache_results' => false,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                    'fields' => 'ids'
                );
                // Only restrict by Elementor popup meta when querying elementor_library
                if ($ppt === 'elementor_library') {
                    $args['meta_query'] = array(
                        array(
                            'key' => '_elementor_template_type',
                            'value' => 'popup'
                        )
                    );
                }
                $ids = get_posts($args);
                if (!empty($ids)) {
                    foreach ($ids as $pid) {
                        if (isset($seen_ids[$pid])) continue;
                        $seen_ids[$pid] = true;
                        $options[] = array('id' => $pid, 'title' => get_the_title($pid));
                    }
                }
            }
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[EGP Debug] ajax_get_target_options popups count: ' . count($options));
            }
        } elseif ($target_type === 'section') {
            // Default provider: saved section/container templates in Elementor
            $options = array();
            $types = apply_filters('egp_section_template_types', array('section','container'));
            $args = array(
                'post_type' => 'elementor_library',
                'post_status' => array('publish','draft','private'),
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'no_found_rows' => true,
                'meta_query' => array(
                    array(
                        'key' => '_elementor_template_type',
                        'value' => $types,
                        'compare' => 'IN'
                    )
                )
            );
            $tpls = get_posts($args);
            foreach ($tpls as $p) { $options[] = array('id' => $p->ID, 'title' => get_the_title($p->ID)); }
            // Allow override/merge via filters
            $options = apply_filters('egp_section_target_options', $options);
        } elseif ($target_type === 'widget') {
            // Default provider: saved global widgets in Elementor
            $options = array();
            $types = apply_filters('egp_widget_template_types', array('widget','global_widget'));
            $args = array(
                'post_type' => 'elementor_library',
                'post_status' => array('publish','draft','private'),
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
                'no_found_rows' => true,
                'meta_query' => array(
                    array(
                        'key' => '_elementor_template_type',
                        'value' => $types,
                        'compare' => 'IN'
                    )
                )
            );
            $tpls = get_posts($args);
            foreach ($tpls as $p) { $options[] = array('id' => $p->ID, 'title' => get_the_title($p->ID)); }
            $options = apply_filters('egp_widget_target_options', $options);
        }

        wp_send_json_success($options);
    }
    
    /**
     * AJAX: Save geo rule from Elementor
     */
    public function ajax_save_elementor_geo_rule() {
        check_ajax_referer('egp_admin_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        error_log('[EGP Debug] ajax_save_elementor_geo_rule called with: ' . print_r($_POST, true));

        $target_type = sanitize_text_field($_POST['target_type'] ?? '');
        $target_id = sanitize_text_field($_POST['target_id'] ?? '');
        $countries = isset($_POST['countries']) ? array_map('sanitize_text_field', $_POST['countries']) : array();
        $priority = intval($_POST['priority'] ?? 50);
        $active = !empty($_POST['active']);
        $title = sanitize_text_field($_POST['title'] ?? '');
        $element_type = sanitize_text_field($_POST['element_type'] ?? '');
        $tracking_id = sanitize_text_field($_POST['tracking_id'] ?? '');

        error_log('[EGP Debug] Processed data: target_type=' . $target_type . ', target_id=' . $target_id . ', countries=' . implode(',', $countries));

        // If an element_ref_id (stable elementor model id) is provided and target_type is section/widget,
        // try to find an existing rule by that ref and update its target_id instead of creating a duplicate
        if (in_array($target_type, array('section','widget'), true) && !empty($element_ref_id)) {
            $existing_by_ref = get_posts(array(
                'post_type' => $this->post_type,
                'post_status' => 'any',
                'meta_query' => array(
                    array('key' => $this->meta_prefix.'element_ref_id', 'value' => (string) $element_ref_id)
                ),
                'fields' => 'ids',
                'posts_per_page' => 1
            ));
            if (!empty($existing_by_ref)) {
                $post_id = intval($existing_by_ref[0]);
                if (!empty($title)) { wp_update_post(array('ID' => $post_id, 'post_title' => $title)); }
                update_post_meta($post_id, $this->meta_prefix.'target_type', $target_type);
                update_post_meta($post_id, $this->meta_prefix.'target_id', (string) $target_id);
                update_post_meta($post_id, $this->meta_prefix.'countries', array_values(array_unique(array_map('strtoupper', (array)$countries))));
                update_post_meta($post_id, $this->meta_prefix.'priority', intval($priority));
                update_post_meta($post_id, $this->meta_prefix.'active', $active ? '1' : '0');
                update_post_meta($post_id, $this->meta_prefix.'source', 'elementor');
                if (!empty($element_type)) { update_post_meta($post_id, $this->meta_prefix.'element_type', $element_type); }
                if (!empty($tracking_id)) { update_post_meta($post_id, $this->meta_prefix.'tracking_id', $tracking_id); }
                update_post_meta($post_id, $this->meta_prefix.'element_ref_id', (string) $element_ref_id);
                wp_send_json_success(array('success'=>true, 'rule_id'=>$post_id, 'updated_by'=>'element_ref_id'));
            }
        }

        $result = $this->save_or_update_rule($target_type, $target_id, $countries, $priority, $active, 'elementor', $title, $element_type, $tracking_id);
        
        if ($result['success']) {
            if (!empty($element_ref_id)) {
                update_post_meta(intval($result['rule_id']), $this->meta_prefix.'element_ref_id', (string) $element_ref_id);
            }
            error_log('[EGP Debug] Elementor rule saved successfully: ' . $result['rule_id']);
            wp_send_json_success($result);
        } else {
            error_log('[EGP Debug] Elementor rule save failed: ' . $result['error']);
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * AJAX: Remove geo rule from Elementor
     */
    public function ajax_remove_elementor_geo_rule() {
        check_ajax_referer('egp_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $element_id = sanitize_text_field($_POST['element_id']);
        
        $existing_rule = $this->get_elementor_geo_rule($element_id);
        
        if ($existing_rule) {
            wp_delete_post($existing_rule->ID, true);
            wp_send_json_success(__('Geo rule removed', 'elementor-geo-popup'));
        } else {
            wp_send_json_success(__('No geo rule found to remove', 'elementor-geo-popup'));
        }
    }
    
    /**
     * Get existing Elementor geo rule by element ID
     */
    private function get_elementor_geo_rule($element_id) {
        $args = array(
            'post_type' => $this->post_type,
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => $this->meta_prefix . 'target_id',
                    'value' => $element_id,
                    'compare' => '='
                ),
                array(
                    'key' => $this->meta_prefix . 'source',
                    'value' => 'elementor',
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        );
        
        $rules = get_posts($args);
        return !empty($rules) ? $rules[0] : null;
    }

    /**
     * Get rule by target type and ID
     */
    public function get_rule_by_target($target_type, $target_id) {
        $args = array(
            'post_type' => $this->post_type,
            'post_status' => 'any',
            'meta_query' => array(
                array('key' => $this->meta_prefix.'target_type', 'value' => $target_type),
                array('key' => $this->meta_prefix.'target_id', 'value' => (string) $target_id)
            ),
            'posts_per_page' => 1
        );
        $rules = get_posts($args);
        return !empty($rules) ? $rules[0] : null;
    }

    /**
     * Create or update a geo_rule for a specific target (popup/elementor)
     */
    public function save_or_update_rule($target_type, $target_id, $countries, $priority, $active, $source, $title, $element_type = null, $tracking_id = null) {
        // Validate required fields
        if (empty($target_type) || empty($target_id)) {
            return array('success' => false, 'error' => 'Missing target_type or target_id');
        }
        
        // Normalize popup: ensure it's an elementor_library popup
        if ($target_type === 'popup') {
            $post = get_post(intval($target_id));
            if (!$post || $post->post_type !== 'elementor_library') {
                return array('success' => false, 'error' => 'Target is not a valid Elementor popup');
            }
            $tpl = get_post_meta($post->ID, '_elementor_template_type', true);
            if ($tpl !== 'popup') {
                return array('success' => false, 'error' => 'Target is not an Elementor popup');
            }
            if (empty($title)) {
                $title = $post->post_title ?: 'Popup #'.$post->ID;
            }
        }
        
        // Find existing rule by target
        $existing = get_posts(array(
            'post_type' => $this->post_type,
            'post_status' => 'any',
            'meta_query' => array(
                array('key' => $this->meta_prefix.'target_type', 'value' => $target_type),
                array('key' => $this->meta_prefix.'target_id', 'value' => (string) $target_id)
            ),
            'fields' => 'ids',
            'posts_per_page' => 1
        ));
        
        if (!empty($existing)) {
            $post_id = $existing[0];
            if (!empty($title)) {
                wp_update_post(array('ID' => $post_id, 'post_title' => $title));
            }
        } else {
            $post_id = wp_insert_post(array(
                'post_title' => $title ?: ucfirst($target_type).' '.$target_id,
                'post_type' => $this->post_type,
                'post_status' => 'publish'
            ));
            if (is_wp_error($post_id)) {
                return array('success' => false, 'error' => 'Failed to create rule');
            }
        }
        
        // Save meta
        update_post_meta($post_id, $this->meta_prefix.'target_type', $target_type);
        update_post_meta($post_id, $this->meta_prefix.'target_id', (string) $target_id);
        update_post_meta($post_id, $this->meta_prefix.'countries', array_values(array_unique(array_map('strtoupper', (array)$countries))));
        update_post_meta($post_id, $this->meta_prefix.'priority', intval($priority));
        update_post_meta($post_id, $this->meta_prefix.'active', $active ? '1' : '0');
        update_post_meta($post_id, $this->meta_prefix.'source', $source);
        
        if (!empty($element_type)) {
            update_post_meta($post_id, $this->meta_prefix.'element_type', $element_type);
        }
        if (!empty($tracking_id)) {
            update_post_meta($post_id, $this->meta_prefix.'tracking_id', $tracking_id);
        }
        
        // Keep Elementor Popup settings in sync so display logic and UI reflect Rule state
        if ($target_type === 'popup') {
            $popup_id = intval($target_id);
            // Ensure this really is an Elementor Popup
            $tpl_type = get_post_meta($popup_id, '_elementor_template_type', true);
            if ($tpl_type === 'popup') {
                $page_settings = get_post_meta($popup_id, '_elementor_page_settings', true);
                if (!is_array($page_settings)) {
                    $page_settings = array();
                }
                // Normalize countries to ISO2 uppercase
                $normalized_countries = array_values(array_unique(array_map('strtoupper', (array) $countries)));
                // Reflect rule active state in Elementor popup settings
                $page_settings['egp_enable_geo_targeting'] = $active ? 'yes' : 'no';
                // Only persist countries if enabled
                if ($active) {
                    $page_settings['egp_countries'] = $normalized_countries;
                }
                update_post_meta($popup_id, '_elementor_page_settings', $page_settings);
            }
        }

        return array('success' => true, 'rule_id' => $post_id);
    }

    /**
     * If a Popup document is saved with geo targeting enabled, ensure a corresponding Rule exists.
     */
    public function maybe_sync_rule_from_popup_settings($post_id, $post, $update) {
        // Basic guards
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        if (get_post_type($post_id) !== 'elementor_library') {
            return;
        }
        // Only handle Elementor Pro Popups
        $tpl_type = get_post_meta($post_id, '_elementor_template_type', true);
        if ($tpl_type !== 'popup') {
            return;
        }
        // Read Elementor Popup page settings
        $page_settings = get_post_meta($post_id, '_elementor_page_settings', true);
        if (!is_array($page_settings)) {
            return;
        }
        $enabled = isset($page_settings['egp_enable_geo_targeting']) && $page_settings['egp_enable_geo_targeting'] === 'yes';
        $countries = isset($page_settings['egp_countries']) && is_array($page_settings['egp_countries']) ? $page_settings['egp_countries'] : array();

        // If enabled and countries provided, save or update a Rule to mirror these settings
        if ($enabled && !empty($countries)) {
            $title = get_the_title($post_id);
            $normalized_countries = array_values(array_unique(array_map('strtoupper', (array) $countries)));
            // Use medium priority default and mark active; source is 'elementor'
            $result = $this->save_or_update_rule('popup', (string) $post_id, $normalized_countries, 50, true, 'elementor', $title, 'popup');
            // Align Elementor-created rules with manual path by syncing back to popup settings
            if (is_array($result) && !empty($result['success']) && !empty($result['rule_id'])) {
                $this->sync_rule_to_popup_settings(intval($result['rule_id']), intval($post_id));
            }
        }
    }

    /**
     * AJAX: Get rule details by Elementor element ID
     */
    public function ajax_get_rule_by_element() {
        // Use generic nonce for tracking to avoid dynamic nonce issues
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'egp_tracking_nonce') &&
            !wp_verify_nonce($_POST['nonce'] ?? '', 'egp_admin_nonce')) {
            wp_die(__('Security check failed', 'elementor-geo-popup'));
        }
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $element_id = isset($_POST['element_id']) ? sanitize_text_field($_POST['element_id']) : '';
        if (!$element_id) {
            wp_send_json_error(__('Missing element_id', 'elementor-geo-popup'));
        }
        
        $rule = $this->get_elementor_geo_rule($element_id);
        if (!$rule) {
            wp_send_json_success(null);
        }
        
        $data = array(
            'id' => $rule->ID,
            'title' => $rule->post_title,
            'type' => get_post_meta($rule->ID, $this->meta_prefix . 'target_type', true),
            'target_id' => get_post_meta($rule->ID, $this->meta_prefix . 'target_id', true),
            'countries' => get_post_meta($rule->ID, $this->meta_prefix . 'countries', true),
            'priority' => get_post_meta($rule->ID, $this->meta_prefix . 'priority', true),
            'active' => get_post_meta($rule->ID, $this->meta_prefix . 'active', true),
            'tracking_id' => get_post_meta($rule->ID, $this->meta_prefix . 'tracking_id', true)
        );
        
        wp_send_json_success($data);
    }


    /**
     * Disable popup geo settings when a geo rule is removed
     */
    public function maybe_disable_popup_on_rule_delete($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== $this->post_type) {
            return;
        }
        $target_type = get_post_meta($post_id, $this->meta_prefix . 'target_type', true);
        $target_id = get_post_meta($post_id, $this->meta_prefix . 'target_id', true);
        if ($target_type !== 'popup' || empty($target_id)) {
            return;
        }
        $popup_id = intval($target_id);
        if ($popup_id <= 0) {
            return;
        }
        $tpl = get_post_meta($popup_id, '_elementor_template_type', true);
        if ($tpl !== 'popup') {
            return;
        }
        $page_settings = get_post_meta($popup_id, '_elementor_page_settings', true);
        if (!is_array($page_settings)) {
            $page_settings = array();
        }
        // Turn off and clear geo fields in a way Elementor UI reads properly
        $page_settings['egp_enable_geo_targeting'] = 'no';
        $page_settings['egp_countries'] = array();
        update_post_meta($popup_id, '_elementor_page_settings', $page_settings);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EGP Debug: Disabled geo-targeting on popup {$popup_id} due to rule {$post_id} deletion");
        }
    }

    /**
     * AJAX: Return target info for a rule (to open in Elementor editor)
     */
    public function ajax_get_rule_target() {
        check_ajax_referer('egp_admin_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        $rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
        if (!$rule_id) {
            wp_send_json_error(__('Invalid rule ID', 'elementor-geo-popup'));
        }
        $target_type = get_post_meta($rule_id, $this->meta_prefix . 'target_type', true);
        $target_id = get_post_meta($rule_id, $this->meta_prefix . 'target_id', true);
        if ($target_type !== 'popup' || empty($target_id)) {
            wp_send_json_error(__('This rule does not target a popup', 'elementor-geo-popup'));
        }
        $pid = intval($target_id);
        $tpl = get_post_meta($pid, '_elementor_template_type', true);
        if ($tpl !== 'popup') {
            wp_send_json_error(__('Target is not an Elementor popup', 'elementor-geo-popup'));
        }
        wp_send_json_success(array('target_id' => $pid));
    }

    /**
     * AJAX: Get rule details by Popup post ID
     */
    public function ajax_get_rule_by_popup() {
        check_ajax_referer('egp_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $popup_id = isset($_POST['popup_id']) ? intval($_POST['popup_id']) : 0;
        if (!$popup_id) {
            wp_send_json_error(__('Missing popup_id', 'elementor-geo-popup'));
        }
        
        $args = array(
            'post_type' => $this->post_type,
            'post_status' => 'any',
            'meta_query' => array(
                array('key' => $this->meta_prefix . 'target_type', 'value' => 'popup'),
                array('key' => $this->meta_prefix . 'target_id', 'value' => (string) $popup_id),
            ),
            'posts_per_page' => 1
        );
        $rules = get_posts($args);
        if (empty($rules)) {
            wp_send_json_success(null);
        }
        $rule = $rules[0];
        $data = array(
            'id' => $rule->ID,
            'title' => $rule->post_title,
            'type' => get_post_meta($rule->ID, $this->meta_prefix . 'target_type', true),
            'target_id' => get_post_meta($rule->ID, $this->meta_prefix . 'target_id', true),
            'countries' => get_post_meta($rule->ID, $this->meta_prefix . 'countries', true),
            'priority' => get_post_meta($rule->ID, $this->meta_prefix . 'priority', true),
            'active' => get_post_meta($rule->ID, $this->meta_prefix . 'active', true),
            'tracking_id' => get_post_meta($rule->ID, $this->meta_prefix . 'tracking_id', true)
        );
        wp_send_json_success($data);
    }

    /**
     * Check if a Group already targets the given element
     */
    private function group_conflict_exists($target_type, $target_id) {
        if (!$target_id) {
            return false;
        }
        global $wpdb;
        $db = RW_Geo_Database::get_instance();
        $variants_table = $db->get_variants_table();
        $mappings_table = $db->get_mappings_table();

        if ($target_type === 'page') {
            // Check default_page_id or mapped page_id
            $exists_default = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$variants_table} WHERE default_page_id = %d LIMIT 1", $target_id));
            if ($exists_default) { return true; }
            $exists_map = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$mappings_table} WHERE page_id = %d LIMIT 1", $target_id));
            return !empty($exists_map);
        }
        if ($target_type === 'popup') {
            $exists_default = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$variants_table} WHERE default_popup_id = %d LIMIT 1", $target_id));
            if ($exists_default) { return true; }
            $exists_map = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$mappings_table} WHERE popup_id = %d LIMIT 1", $target_id));
            return !empty($exists_map);
        }
        return false;
    }
    
    /**
     * Enqueue editor scripts
     */
    public function enqueue_editor_scripts() {
        wp_enqueue_script(
            'egp-editor',
            EGP_PLUGIN_URL . 'assets/js/editor.js',
            array('jquery', 'elementor-editor'),
            (function_exists('filemtime') ? @filemtime(EGP_PLUGIN_DIR . 'assets/js/editor.js') : EGP_VERSION),
            true
        );
        
        // Determine current Elementor document ID and type (best-effort)
        $doc_id = 0;
        if (!empty($_GET['elementor-preview'])) {
            $doc_id = intval($_GET['elementor-preview']);
        } elseif (!empty($_GET['post'])) {
            $doc_id = intval($_GET['post']);
        }
        $doc_type = '';
        $is_popup = false;
        if ($doc_id) {
            $p = get_post($doc_id);
            if ($p && $p->post_type === 'elementor_library') {
                $tpl = get_post_meta($p->ID, '_elementor_template_type', true);
                if ($tpl === 'popup') {
                    $doc_type = 'popup';
                    $is_popup = true;
                }
            }
        }
        
        wp_localize_script('egp-editor', 'egpEditor', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('egp_admin_nonce'),
            'isPro' => $this->is_pro_user(),
            'documentId' => $doc_id,
            'documentType' => $doc_type,
            'isPopup' => $is_popup
        ));
    }
    
    /**
     * Register dynamic tags
     */
    public function register_dynamic_tags($dynamic_tags_manager) {
        // This will be implemented when we add widget targeting
    }

    /**
     * Sync rule settings to Elementor popup settings
     */
    private function sync_rule_to_popup_settings($rule_id, $popup_id) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EGP Debug: Starting sync for rule {$rule_id} to popup {$popup_id}");
        }
        
        $tpl_type = get_post_meta($popup_id, '_elementor_template_type', true);
        if ($tpl_type !== 'popup') {
            if (defined('WP_DEBUG') && WP_DEBUG) { 
                error_log("EGP Debug: Popup {$popup_id} is not an Elementor popup (template type: {$tpl_type})"); 
            }
            return;
        }
        
        $page_settings = get_post_meta($popup_id, '_elementor_page_settings', true);
        if (!is_array($page_settings)) { 
            $page_settings = array(); 
        }

        $target_type = get_post_meta($rule_id, $this->meta_prefix . 'target_type', true);
        $target_id = get_post_meta($rule_id, $this->meta_prefix . 'target_id', true);
        $countries = get_post_meta($rule_id, $this->meta_prefix . 'countries', true);
        $active = get_post_meta($rule_id, $this->meta_prefix . 'active', true);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EGP Debug: Rule data - target_type: {$target_type}, target_id: {$target_id}, active: {$active}, countries: " . print_r($countries, true));
        }

        $normalized_countries = array_values(array_unique(array_map('strtoupper', (array) $countries)));

        $page_settings['egp_enable_geo_targeting'] = $active === '1' ? 'yes' : 'no';
        $page_settings['egp_countries'] = $normalized_countries;
        // Ensure sane close behavior for Elementor popups
        if (!isset($page_settings['prevent_close_on_background'])) {
            $page_settings['prevent_close_on_background'] = '';
        }
        if (!isset($page_settings['prevent_close_on_esc'])) {
            $page_settings['prevent_close_on_esc'] = '';
        }

        $result = update_post_meta($popup_id, '_elementor_page_settings', $page_settings);
        
        if (defined('WP_DEBUG') && WP_DEBUG) { 
            error_log("EGP Debug: Synced rule {$rule_id} to popup {$popup_id} - Active: {$active}, Countries: " . implode(',', $normalized_countries) . ", Update result: " . var_export($result, true)); 
        }
    }

    /**
     * Add popup geo filter script to footer
     */
    private function add_popup_geo_filter() {
        if (is_admin()) {
            return;
        }

        $user_country = $this->get_user_country();
        
        // Get fallback popup setting
        $fallback_popup_id = get_option('egp_default_popup_id', '');
        $fallback_behavior = get_option('egp_fallback_behavior', 'show_to_all');
        
        // Get all popups with geo-targeting enabled
        $popups = get_posts(array(
            'post_type' => 'elementor_library',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_elementor_template_type',
                    'value' => 'popup'
                ),
                array(
                    'key' => '_elementor_page_settings',
                    'value' => 'egp_enable_geo_targeting',
                    'compare' => 'LIKE'
                )
            ),
            'posts_per_page' => -1
        ));
        
        $popup_data = array();
        foreach ($popups as $popup) {
            $page_settings = get_post_meta($popup->ID, '_elementor_page_settings', true);
            if (is_array($page_settings) && isset($page_settings['egp_enable_geo_targeting']) && $page_settings['egp_enable_geo_targeting'] === 'yes') {
                $countries = isset($page_settings['egp_countries']) ? $page_settings['egp_countries'] : array();
                $popup_data[$popup->ID] = array(
                    'id' => $popup->ID,
                    'title' => $popup->post_title,
                    'countries' => $countries
                );
            }
        }
        
        if (empty($popup_data)) {
            return;
        }
        
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var userCountry = "' . esc_js($user_country) . '";
            var popupData = ' . wp_json_encode($popup_data) . ';
            var fallbackPopupId = "' . esc_js($fallback_popup_id) . '";
            var fallbackBehavior = "' . esc_js($fallback_behavior) . '";
            
            // Add geo-targeting as an additional condition to Elementor popups
            if (typeof elementorFrontend !== "undefined") {
                // Store original popup show method
                var originalShowPopup = elementorFrontend.documents.manager.documents[0].showPopup;
                
                // Override the showPopup method to add geo-targeting check
                elementorFrontend.documents.manager.documents[0].showPopup = function(popupId) {
                    // Check if this popup has geo-targeting enabled
                    if (popupData[popupId]) {
                        var allowedCountries = popupData[popupId].countries;
                        if (allowedCountries && allowedCountries.length > 0) {
                            // Check if user\'s country is in the allowed list
                            if (!allowedCountries.includes(userCountry.toUpperCase())) {
                                // Country doesn\'t match - handle based on fallback behavior
                                if (window.console && console.log) {
                                    console.log("EGP: Popup " + popupId + " blocked - user country " + userCountry + " not in allowed list: " + allowedCountries.join(", "));
                                }
                                
                                // If fallback popup is configured and behavior allows it, show fallback
                                if (fallbackPopupId && fallbackPopupId !== "" && fallbackBehavior === "show_fallback") {
                                    if (window.console && console.log) {
                                        console.log("EGP: Showing fallback popup " + fallbackPopupId + " instead");
                                    }
                                    return originalShowPopup.call(this, fallbackPopupId);
                                }
                                
                                // Otherwise, don\'t show any popup
                                return false;
                            }
                        }
                        // Allowed → track a view
                        try { if (window.egpTrackView) { window.egpTrackView(null, popupId); } } catch(e) {}
                    }
                    
                    // Country check passed (or no geo-targeting) - proceed with original popup logic
                    return originalShowPopup.call(this, popupId);
                };
                
                // Also override the popup trigger method
                var originalTriggerPopup = elementorFrontend.documents.manager.documents[0].triggerPopup;
                elementorFrontend.documents.manager.documents[0].triggerPopup = function(popupId) {
                    // Check if this popup has geo-targeting enabled
                    if (popupData[popupId]) {
                        var allowedCountries = popupData[popupId].countries;
                        if (allowedCountries && allowedCountries.length > 0) {
                            // Check if user\'s country is in the allowed list
                            if (!allowedCountries.includes(userCountry.toUpperCase())) {
                                // Country doesn\'t match - handle based on fallback behavior
                                if (window.console && console.log) {
                                    console.log("EGP: Popup " + popupId + " trigger blocked - user country " + userCountry + " not in allowed list: " + allowedCountries.join(", "));
                                }
                                
                                // If fallback popup is configured and behavior allows it, trigger fallback
                                if (fallbackPopupId && fallbackPopupId !== "" && fallbackBehavior === "show_fallback") {
                                    if (window.console && console.log) {
                                        console.log("EGP: Triggering fallback popup " + fallbackPopupId + " instead");
                                    }
                                    return originalTriggerPopup.call(this, fallbackPopupId);
                                }
                                
                                // Otherwise, don\'t trigger any popup
                                return false;
                            }
                        }
                        // Allowed → track a view on trigger as well (covers direct triggers)
                        try { if (window.egpTrackView) { window.egpTrackView(null, popupId); } } catch(e) {}
                    }
                    
                    // Country check passed (or no geo-targeting) - proceed with original trigger logic
                    return originalTriggerPopup.call(this, popupId);
                };
            }
        });
        </script>';
    }
}

// Initialize the Geo Rules system
EGP_Geo_Rules::get_instance();

/**
 * Elementor Advanced Tab: Register a Geo Targeting section for containers, sections, columns, all widgets, and forms
 * This complements the JS editor panel injection and is robust across Elementor versions (incl. Containers).
 */
// Elementor controls are now registered in the main plugin class
// Elementor hooks are now registered in the main plugin class on elementor/init
// This ensures proper timing and avoids conflicts
