<?php
/**
 * Elementor Controls Fix - Resolves country selection and rule management issues
 * 
 * @package ElementorGeoPopup
 * @since 1.0.3
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Elementor Controls Fix Class
 */
class EGP_Elementor_Controls_Fix {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // DISABLED: Main plugin now handles all controls via if-so pattern
        // The duplicate "enhanced" controls were causing conflicts
        
        // Keep only the AJAX handlers for enhanced rule management
        add_action('wp_ajax_egp_save_elementor_rule_enhanced', array($this, 'ajax_save_elementor_rule_enhanced'));
        add_action('wp_ajax_egp_get_element_rule', array($this, 'ajax_get_element_rule'));
        add_action('wp_ajax_egp_update_element_settings', array($this, 'ajax_update_element_settings'));
        
        // Fix admin rule editing
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_fix_scripts'));
    }
    
    /**
     * Register fixed Elementor controls
     */
    public function register_fixed_controls() {
        // Remove existing problematic controls and re-register with fixes
        $this->register_enhanced_geo_controls();
    }
    
    /**
     * Register enhanced geo controls with proper country selection
     */
    private function register_enhanced_geo_controls() {
        // Enhanced controls for sections
        add_action('elementor/element/section/section_advanced/after_section_end', array($this, 'add_enhanced_geo_controls'), 25, 2);
        
        // Enhanced controls for containers (Elementor 3.0+)
        add_action('elementor/element/container/section_advanced/after_section_end', array($this, 'add_enhanced_geo_controls'), 25, 2);
        
        // Enhanced controls for all widgets
        add_action('elementor/element/common/section_advanced/after_section_end', array($this, 'add_enhanced_geo_controls'), 25, 2);
    }
    
    /**
     * Add enhanced geo targeting controls
     */
    public function add_enhanced_geo_controls($element, $args = null) {
        $element_name = method_exists($element, 'get_name') ? $element->get_name() : 'unknown';
        $element_type = method_exists($element, 'get_type') ? $element->get_type() : 'unknown';
        
        // Check if controls already exist to prevent duplicates
        $controls = $element->get_controls();
        if (isset($controls['egp_geo_enhanced'])) {
            return;
        }
        
        $element->start_controls_section(
            'egp_geo_enhanced',
            array(
                'label' => __('Geo Targeting (Enhanced)', 'elementor-geo-popup'),
                'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
            )
        );
        
        // Element ID display and management
        $element->add_control(
            'egp_element_id_enhanced',
            array(
                'label'       => __('Element ID', 'elementor-geo-popup'),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'description' => __('Unique ID for this element. Auto-generated if empty.', 'elementor-geo-popup'),
                'placeholder' => __('Auto-generated', 'elementor-geo-popup'),
                'default'     => '',
            )
        );
        
        // Enable geo targeting
        $element->add_control(
            'egp_geo_enabled_enhanced',
            array(
                'label'        => __('Enable Geo Targeting', 'elementor-geo-popup'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => __('Yes', 'elementor-geo-popup'),
                'label_off'    => __('No', 'elementor-geo-popup'),
                'return_value' => 'yes',
                'default'      => '',
            )
        );
        
        // Countries selection using REPEATER for better persistence
        $element->add_control(
            'egp_countries_enhanced',
            array(
                'label'       => __('Target Countries', 'elementor-geo-popup'),
                'type'        => \Elementor\Controls_Manager::RAW_HTML,
                'raw'         => $this->get_countries_selector_html(),
                'condition'   => array('egp_geo_enabled_enhanced' => 'yes'),
            )
        );
        
        // Hidden field to store selected countries
        $element->add_control(
            'egp_countries_data',
            array(
                'type'        => \Elementor\Controls_Manager::HIDDEN,
                'default'     => '',
                'condition'   => array('egp_geo_enabled_enhanced' => 'yes'),
            )
        );
        
        // Priority
        $element->add_control(
            'egp_priority_enhanced',
            array(
                'label'       => __('Priority', 'elementor-geo-popup'),
                'type'        => \Elementor\Controls_Manager::NUMBER,
                'min'         => 1,
                'max'         => 100,
                'step'        => 1,
                'default'     => 50,
                'condition'   => array('egp_geo_enabled_enhanced' => 'yes'),
                'description' => __('Higher numbers take precedence (1-100)', 'elementor-geo-popup'),
            )
        );
        
        // Rule management buttons
        $element->add_control(
            'egp_rule_actions',
            array(
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw'  => $this->get_rule_actions_html(),
                'condition' => array('egp_geo_enabled_enhanced' => 'yes'),
            )
        );
        
        $element->end_controls_section();
    }
    
    /**
     * Get countries selector HTML with enhanced functionality
     */
    private function get_countries_selector_html() {
        $countries = $this->get_countries_list();
        
        ob_start();
        ?>
        <div class="egp-countries-selector-enhanced">
            <select id="egp-countries-select" multiple style="width: 100%; min-height: 120px;">
                <?php foreach ($countries as $code => $name): ?>
                    <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php _e('Hold Ctrl/Cmd to select multiple countries. Changes are saved automatically.', 'elementor-geo-popup'); ?></p>
            <div id="egp-selected-countries" style="margin-top: 10px; padding: 8px; background: #f8f9fa; border-radius: 4px; display: none;">
                <strong><?php _e('Selected:', 'elementor-geo-popup'); ?></strong>
                <span id="egp-selected-list"></span>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var $select = $('#egp-countries-select');
            var $selectedDiv = $('#egp-selected-countries');
            var $selectedList = $('#egp-selected-list');
            
            // Load existing selections
            function loadExistingSelections() {
                var panel = elementor.getPanelView().getCurrentPageView();
                if (panel && panel.model) {
                    var settings = panel.model.get('settings');
                    if (settings) {
                        var countriesData = settings.get('egp_countries_data');
                        if (countriesData) {
                            try {
                                var countries = JSON.parse(countriesData);
                                $select.val(countries);
                                updateSelectedDisplay();
                            } catch (e) {
                                console.log('EGP: Error parsing countries data:', e);
                            }
                        }
                    }
                }
            }
            
            // Update selected countries display
            function updateSelectedDisplay() {
                var selected = $select.val() || [];
                if (selected.length > 0) {
                    var names = selected.map(function(code) {
                        return $select.find('option[value="' + code + '"]').text();
                    });
                    $selectedList.text(names.join(', '));
                    $selectedDiv.show();
                } else {
                    $selectedDiv.hide();
                }
            }
            
            // Save selections to Elementor model
            function saveSelections() {
                var selected = $select.val() || [];
                var panel = elementor.getPanelView().getCurrentPageView();
                if (panel && panel.model) {
                    var settings = panel.model.get('settings');
                    if (settings && typeof settings.set === 'function') {
                        settings.set('egp_countries_data', JSON.stringify(selected));
                        updateSelectedDisplay();
                        
                        // Auto-save rule
                        setTimeout(function() {
                            saveEnhancedRule();
                        }, 500);
                    }
                }
            }
            
            // Bind events
            $select.on('change', saveSelections);
            
            // Load existing data when panel opens
            setTimeout(loadExistingSelections, 100);
            
            // Re-load when section is activated
            if (typeof elementor !== 'undefined' && elementor.channels && elementor.channels.editor) {
                elementor.channels.editor.on('section:activated', function(sectionName) {
                    if (sectionName === 'egp_geo_enhanced') {
                        setTimeout(loadExistingSelections, 100);
                    }
                });
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get rule actions HTML
     */
    private function get_rule_actions_html() {
        ob_start();
        ?>
        <div class="egp-rule-actions">
            <button type="button" id="egp-save-rule" class="elementor-button elementor-button-success" style="margin-right: 8px;">
                <?php _e('Save Rule', 'elementor-geo-popup'); ?>
            </button>
            <button type="button" id="egp-test-rule" class="elementor-button elementor-button-default">
                <?php _e('Test Rule', 'elementor-geo-popup'); ?>
            </button>
            <div id="egp-rule-status" style="margin-top: 8px; padding: 6px; border-radius: 3px; display: none;"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#egp-save-rule').on('click', function() {
                saveEnhancedRule();
            });
            
            $('#egp-test-rule').on('click', function() {
                testEnhancedRule();
            });
        });
        
        function saveEnhancedRule() {
            var panel = elementor.getPanelView().getCurrentPageView();
            if (!panel || !panel.model) {
                showRuleStatus('error', 'No element selected');
                return;
            }
            
            var settings = panel.model.get('settings');
            if (!settings) {
                showRuleStatus('error', 'Cannot access element settings');
                return;
            }
            
            var enabled = settings.get('egp_geo_enabled_enhanced') === 'yes';
            if (!enabled) {
                showRuleStatus('warning', 'Geo targeting is disabled');
                return;
            }
            
            var countriesData = settings.get('egp_countries_data');
            var countries = [];
            if (countriesData) {
                try {
                    countries = JSON.parse(countriesData);
                } catch (e) {
                    console.log('EGP: Error parsing countries:', e);
                }
            }
            
            if (countries.length === 0) {
                showRuleStatus('error', 'Please select at least one country');
                return;
            }
            
            var elementId = settings.get('egp_element_id_enhanced') || panel.model.get('id');
            var priority = settings.get('egp_priority_enhanced') || 50;
            var elementType = panel.model.get('elType') || 'widget';
            
            // Auto-generate element ID if empty
            if (!elementId) {
                elementId = 'geo_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
                settings.set('egp_element_id_enhanced', elementId);
            }
            
            var data = {
                action: 'egp_save_elementor_rule_enhanced',
                nonce: egpEditor.nonce,
                element_id: elementId,
                element_type: elementType,
                countries: countries,
                priority: priority,
                active: true,
                title: elementType.charAt(0).toUpperCase() + elementType.slice(1) + ' ' + elementId,
                document_id: egpEditor.documentId || 0
            };
            
            showRuleStatus('loading', 'Saving rule...');
            
            jQuery.post(egpEditor.ajaxUrl, data, function(response) {
                if (response.success) {
                    showRuleStatus('success', 'Rule saved successfully! ID: ' + response.data.rule_id);
                } else {
                    showRuleStatus('error', 'Save failed: ' + (response.data || 'Unknown error'));
                }
            }).fail(function() {
                showRuleStatus('error', 'Network error occurred');
            });
        }
        
        function testEnhancedRule() {
            showRuleStatus('info', 'Testing rule... (Feature coming soon)');
        }
        
        function showRuleStatus(type, message) {
            var $status = jQuery('#egp-rule-status');
            var colors = {
                success: '#46b450',
                error: '#dc3232',
                warning: '#ffb900',
                info: '#00a0d2',
                loading: '#666'
            };
            
            $status.css({
                'background-color': colors[type] || '#666',
                'color': 'white',
                'display': 'block'
            }).text(message);
            
            if (type !== 'loading') {
                setTimeout(function() {
                    $status.fadeOut();
                }, 3000);
            }
        }
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Enhanced AJAX handler for saving Elementor rules
     */
    public function ajax_save_elementor_rule_enhanced() {
        check_ajax_referer('egp_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $element_id = sanitize_text_field($_POST['element_id'] ?? '');
        $element_type = sanitize_text_field($_POST['element_type'] ?? '');
        $countries = isset($_POST['countries']) ? array_map('sanitize_text_field', $_POST['countries']) : array();
        $priority = intval($_POST['priority'] ?? 50);
        $active = !empty($_POST['active']);
        $title = sanitize_text_field($_POST['title'] ?? '');
        $document_id = intval($_POST['document_id'] ?? 0);
        
        if (empty($element_id) || empty($countries)) {
            wp_send_json_error('Missing required fields');
        }
        
        // Check for existing rule by element_id to prevent duplicates
        $existing_rule = get_posts(array(
            'post_type' => 'geo_rule',
            'meta_query' => array(
                array(
                    'key' => 'egp_target_id',
                    'value' => $element_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'egp_target_type',
                    'value' => $element_type,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        ));
        
        if (!empty($existing_rule)) {
            // Update existing rule
            $rule_id = $existing_rule[0]->ID;
            wp_update_post(array(
                'ID' => $rule_id,
                'post_title' => $title
            ));
        } else {
            // Create new rule
            $rule_id = wp_insert_post(array(
                'post_title' => $title,
                'post_type' => 'geo_rule',
                'post_status' => 'publish'
            ));
            
            if (is_wp_error($rule_id)) {
                wp_send_json_error('Failed to create rule');
            }
        }
        
        // Update rule metadata
        update_post_meta($rule_id, 'egp_target_type', $element_type);
        update_post_meta($rule_id, 'egp_target_id', $element_id);
        update_post_meta($rule_id, 'egp_element_id', $element_id);
        update_post_meta($rule_id, 'egp_element_type', $element_type);
        update_post_meta($rule_id, 'egp_countries', array_map('strtoupper', $countries));
        update_post_meta($rule_id, 'egp_priority', $priority);
        update_post_meta($rule_id, 'egp_active', $active ? '1' : '0');
        update_post_meta($rule_id, 'egp_source', 'elementor_enhanced');
        
        if ($document_id > 0) {
            update_post_meta($rule_id, 'egp_document_id', $document_id);
        }
        
        wp_send_json_success(array(
            'rule_id' => $rule_id,
            'message' => 'Rule saved successfully',
            'updated' => !empty($existing_rule)
        ));
    }
    
    /**
     * Get element rule data
     */
    public function ajax_get_element_rule() {
        check_ajax_referer('egp_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $element_id = sanitize_text_field($_POST['element_id'] ?? '');
        if (empty($element_id)) {
            wp_send_json_error('Missing element_id');
        }
        
        $rule = get_posts(array(
            'post_type' => 'geo_rule',
            'meta_query' => array(
                array(
                    'key' => 'egp_target_id',
                    'value' => $element_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        ));
        
        if (empty($rule)) {
            wp_send_json_success(null);
        }
        
        $rule = $rule[0];
        $data = array(
            'id' => $rule->ID,
            'title' => $rule->post_title,
            'countries' => get_post_meta($rule->ID, 'egp_countries', true),
            'priority' => get_post_meta($rule->ID, 'egp_priority', true),
            'active' => get_post_meta($rule->ID, 'egp_active', true) === '1'
        );
        
        wp_send_json_success($data);
    }
    
    /**
     * Update element settings
     */
    public function ajax_update_element_settings() {
        check_ajax_referer('egp_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // This will be used for syncing settings between Elementor and rules
        wp_send_json_success('Settings updated');
    }
    
    /**
     * Enqueue admin fix scripts
     */
    public function enqueue_admin_fix_scripts($hook) {
        if ($hook !== 'edit.php' && $hook !== 'post.php') {
            return;
        }
        
        global $post_type;
        if ($post_type !== 'geo_rule') {
            return;
        }
        
        wp_enqueue_script(
            'egp-admin-fix',
            EGP_PLUGIN_URL . 'assets/js/admin-fix.js',
            array('jquery'),
            EGP_VERSION,
            true
        );
        
        wp_localize_script('egp-admin-fix', 'egpAdminFix', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('egp_admin_nonce'),
            'elementorUrl' => admin_url('post.php?action=elementor&post=')
        ));
    }
    
    /**
     * Enqueue enhanced editor scripts
     */
    public function enqueue_enhanced_editor_scripts() {
        wp_enqueue_script(
            'egp-editor-enhanced',
            EGP_PLUGIN_URL . 'assets/js/editor-enhanced.js',
            array('jquery', 'elementor-editor'),
            EGP_VERSION,
            true
        );
        
        wp_localize_script('egp-editor-enhanced', 'egpEditorEnhanced', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('egp_admin_nonce'),
            'countries' => $this->get_countries_list()
        ));
    }
    
    /**
     * Get countries list (canonical: egp_get_country_options).
     */
    private function get_countries_list() {
        return function_exists( 'egp_get_country_options' ) ? egp_get_country_options() : array();
    }
}

// Initialize the fix
EGP_Elementor_Controls_Fix::get_instance();