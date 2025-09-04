<?php
/**
 * Variant Groups Admin Interface
 * Manages geo variant groups and their country mappings
 * 
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Variant Groups Admin Page
 */
class RW_Geo_Variant_Groups_Admin {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_rw_geo_save_variant', array($this, 'ajax_save_variant'));
        add_action('wp_ajax_rw_geo_delete_variant', array($this, 'ajax_delete_variant'));
        add_action('wp_ajax_rw_geo_save_mapping', array($this, 'ajax_save_mapping'));
        add_action('wp_ajax_rw_geo_delete_mapping', array($this, 'ajax_delete_mapping'));
        add_action('wp_ajax_rw_geo_get_pages', array($this, 'ajax_get_pages'));
        add_action('wp_ajax_rw_geo_get_popups', array($this, 'ajax_get_popups'));
        add_action('wp_ajax_rw_geo_search_countries', array($this, 'ajax_search_countries'));
        add_action('wp_ajax_rw_geo_preview_mapping', array($this, 'ajax_preview_mapping'));
    }
    
    /**
     * Add admin page to menu
     */
    public function add_admin_page() {
        // Register Groups submenu and move above Rules by giving a higher priority hook
        add_submenu_page(
            'geo-elementor',
            __('Groups', 'elementor-geo-popup'),
            __('Groups', 'elementor-geo-popup'),
            'manage_options',
            'geo-elementor-variants',
            array($this, 'render_admin_page'),
            1
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'geo-elementor_page_geo-elementor-variants') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_style('wp-admin');
        wp_enqueue_style('wp-list-table');
        
        // Reuse global admin styles (cards, layout)
        wp_enqueue_style(
            'egp-admin-style',
            EGP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            EGP_VERSION
        );

        wp_enqueue_style(
            'rw-geo-variants-admin',
            EGP_PLUGIN_URL . 'assets/css/variants-admin.css',
            array('wp-admin','egp-admin-style'),
            EGP_VERSION
        );
        
        wp_enqueue_script(
            'rw-geo-variants-admin',
            EGP_PLUGIN_URL . 'assets/js/variants-admin.js',
            array('jquery', 'jquery-ui-sortable'),
            EGP_VERSION,
            true
        );

        // Inline script for preview action
        add_action('admin_print_footer_scripts', function () {
            ?>
            <script type="text/javascript">
            (function($){
                $(document).on('click', '#egp-preview-country', function(){
                    var cc = $('#egp-test-country').val() || '';
                    if (!cc) { return; }
                    var $out = $('#egp-preview-result').text('<?php echo esc_js(__('Resolving...', 'elementor-geo-popup')); ?>');
                    $.post(ajaxurl, { action: 'rw_geo_preview_mapping', nonce: '<?php echo wp_create_nonce('rw_geo_variants_nonce'); ?>', country: cc }, function(resp){
                        if (resp && resp.success && resp.data){
                            var s = [];
                            if (resp.data.variant){ s.push('Group: '+resp.data.variant.name+' ('+resp.data.variant.slug+')'); }
                            if (resp.data.mapping){
                                if (resp.data.mapping.popup_id){ s.push('Popup ID: '+resp.data.mapping.popup_id); }
                                if (resp.data.mapping.page_id){ s.push('Page ID: '+resp.data.mapping.page_id); }
                            }
                            $out.text(s.join(' • ') || '<?php echo esc_js(__('No mapping resolved', 'elementor-geo-popup')); ?>');
                        } else {
                            $out.text((resp && resp.data) ? resp.data : '<?php echo esc_js(__('No mapping resolved', 'elementor-geo-popup')); ?>');
                        }
                    });
                });
            })(jQuery);
            </script>
            <?php
        });
        
        wp_localize_script('rw-geo-variants-admin', 'rwGeoVariants', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rw_geo_variants_nonce'),
            'debug' => (bool) get_option('egp_debug_mode'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this group?', 'elementor-geo-popup'),
                'confirmDeleteMapping' => __('Are you sure you want to delete this mapping?', 'elementor-geo-popup'),
                'saving' => __('Saving...', 'elementor-geo-popup'),
                'saved' => __('Saved!', 'elementor-geo-popup'),
                'error' => __('Error occurred', 'elementor-geo-popup')
            )
        ));

        // Dequeue Select2/SelectWoo to avoid conflicts on this screen
        add_action('admin_print_scripts', function () {
            wp_dequeue_script('select2');
            wp_dequeue_script('selectWoo');
        }, 100);

        add_action('admin_print_styles', function () {
            wp_dequeue_style('select2');
            wp_dequeue_style('selectWoo');
        }, 100);

        // Dequeue any problematic admin_script.js loaded by other plugins on this screen
        add_action('admin_print_scripts', function () {
            global $wp_scripts;
            if (!isset($wp_scripts) || empty($wp_scripts->queue)) {
                return;
            }
            foreach ($wp_scripts->queue as $handle) {
                if (isset($wp_scripts->registered[$handle]) && isset($wp_scripts->registered[$handle]->src)) {
                    $src = $wp_scripts->registered[$handle]->src;
                    if (strpos($src, 'admin_script.js') !== false || strpos($src, 'admin-script.js') !== false) {
                        wp_dequeue_script($handle);
                    }
                }
            }
        }, 100);
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $action = $_GET['action'] ?? 'list';
        
        echo '<div class="wrap egp-settings">';
        echo '<div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">';
        echo '<img src="' . esc_url( EGP_PLUGIN_URL . 'assets/img/GeoElementor.svg' ) . '" alt="Geo Elementor" style="height:32px;width:auto;vertical-align:middle;" />';
        echo '<h1 style="margin:0;line-height:1;">' . __('Groups', 'elementor-geo-popup') . '</h1>';
        echo '</div>';
        echo '<div class="egp-section-card">';
        echo '<p style="margin:0 0 8px 0;">' . __('Manage variant Groups that map content (Pages/Popups/Sections/Widgets) to countries.', 'elementor-geo-popup') . '</p>';
        echo '<p style="margin:0 0 8px 0;">' . __('Tip: Use a default target for the group, then add specific overrides for selected countries. Rules on the same target take precedence and may block group mappings.', 'elementor-geo-popup') . ' <span class="dashicons dashicons-editor-help" title="If a Rule already targets the same Page/Popup, the Group mapping will not apply to avoid conflicts."></span></p>';
        echo '</div>';
        
        switch ($action) {
            case 'add':
            case 'edit':
                $this->render_edit_form();
                break;
            default:
                $this->render_list_table();
                break;
        }
        
        echo '</div>';
    }
    
    /**
     * Render list table
     */
    private function render_list_table() {
        $variant_crud = new RW_Geo_Variant_CRUD();
        $variants = $variant_crud->get_all();
        
        echo '<div class="egp-section-card">';
        echo '<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">';
        echo '<a href="' . admin_url('admin.php?page=geo-elementor-variants&action=add') . '" class="button button-primary">' . __('Add New Group', 'elementor-geo-popup') . '</a>';
        echo '<div class="egp-test-country" style="margin-left:auto;display:flex;gap:8px;align-items:center;">';
        echo '<label for="egp-test-country" style="margin:0;">' . __('Test as Country', 'elementor-geo-popup') . '</label>';
        echo '<select id="egp-test-country" class="egp-enhanced-input" style="min-width:180px;">';
        // Populate simple country list (subset) for quick testing; full search done server-side
        $quick = array('US'=>'United States','GB'=>'United Kingdom','RO'=>'Romania','SG'=>'Singapore','DE'=>'Germany','FR'=>'France');
        foreach ($quick as $cc=>$nn){ echo '<option value="' . esc_attr($cc) . '">' . esc_html($nn) . '</option>'; }
        echo '</select>';
        echo '<button type="button" id="egp-preview-country" class="button">' . __('Preview', 'elementor-geo-popup') . '</button>';
        echo '</div>';
        echo '</div>';
        echo '<div id="egp-preview-result" style="margin-top:8px;color:#555;"></div>';
        
        if (empty($variants)) {
            echo '<div class="notice notice-info"><p>' . __('No groups found. Create your first one to get started with geo-targeting.', 'elementor-geo-popup') . '</p></div>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Name', 'elementor-geo-popup') . '</th>';
        echo '<th>' . __('Slug', 'elementor-geo-popup') . '</th>';
        echo '<th>' . __('Types', 'elementor-geo-popup') . ' <span class="dashicons dashicons-editor-help" title="Which targets this group can map."></span></th>';
        echo '<th>' . __('Countries', 'elementor-geo-popup') . ' <span class="dashicons dashicons-editor-help" title="Number of country-specific overrides."></span></th>';
        echo '<th>' . __('Updated', 'elementor-geo-popup') . '</th>';
        echo '<th>' . __('Actions', 'elementor-geo-popup') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($variants as $variant) {
            $mapping_crud = new RW_Geo_Mapping_CRUD();
            $mappings = $mapping_crud->get_by_variant($variant->id);
            $country_count = count($mappings);
            $has_section_ref = false;
            $has_widget_ref = false;
            $section_refs = array();
            $widget_refs = array();
            foreach ($mappings as $m) {
                if (!empty($m->section_ref)) { 
                    $has_section_ref = true; 
                    $ref = ltrim(trim($m->section_ref), '#');
                    if ($ref !== '' && !in_array($ref, $section_refs, true)) { $section_refs[] = $ref; }
                }
                if (!empty($m->widget_ref)) { 
                    $has_widget_ref = true; 
                    $ref = ltrim(trim($m->widget_ref), '#');
                    if ($ref !== '' && !in_array($ref, $widget_refs, true)) { $widget_refs[] = $ref; }
                }
            }
            $section_preview = implode(', ', array_slice($section_refs, 0, 3));
            $widget_preview = implode(', ', array_slice($widget_refs, 0, 3));
            
            $types = array();
            if ($variant->type_mask & RW_GEO_TYPE_PAGE) $types[] = 'Page';
            if ($variant->type_mask & RW_GEO_TYPE_POPUP) $types[] = 'Popup';
            if ($variant->type_mask & RW_GEO_TYPE_SECTION) $types[] = 'Section';
            if ($variant->type_mask & RW_GEO_TYPE_WIDGET) $types[] = 'Widget';
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($variant->name) . '</strong>';
            if ($has_section_ref) {
                $title = $section_preview ? esc_attr__('Refs: ', 'elementor-geo-popup') . esc_attr($section_preview) : esc_attr__('Section refs present', 'elementor-geo-popup');
                echo ' <span class="egp-badge" title="' . $title . '" style="background:#e3f2fd;color:#1565c0;border:1px solid #90caf9;border-radius:3px;padding:2px 6px;font-size:10px;">Section Ref</span>';
            }
            if ($has_widget_ref) {
                $title = $widget_preview ? esc_attr__('Refs: ', 'elementor-geo-popup') . esc_attr($widget_preview) : esc_attr__('Widget refs present', 'elementor-geo-popup');
                echo ' <span class="egp-badge" title="' . $title . '" style="background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;border-radius:3px;padding:2px 6px;font-size:10px;">Widget Ref</span>';
            }
            echo '</td>';
            echo '<td><code>' . esc_html($variant->slug) . '</code></td>';
            echo '<td>' . implode(', ', $types) . '</td>';
            $badges = array();
            if ($country_count === 0) { $badges[] = '<span class="egp-badge" style="background:#fff3cd;color:#856404;border:1px solid #ffeaa7;border-radius:3px;padding:2px 6px;">' . __('Incomplete', 'elementor-geo-popup') . '</span>'; }
            echo '<td>' . sprintf(_n('%d country', '%d countries', $country_count, 'elementor-geo-popup'), $country_count) . ' ' . implode(' ', $badges) . '</td>';
            echo '<td>' . esc_html(date_i18n(get_option('date_format'), strtotime($variant->updated_at))) . '</td>';
            echo '<td>';
            echo '<a href="' . admin_url('admin.php?page=geo-elementor-variants&action=edit&id=' . $variant->id) . '" class="button button-small">' . __('Edit', 'elementor-geo-popup') . '</a> ';
            echo '<button class="button button-small button-link-delete delete-variant" data-id="' . $variant->id . '">' . __('Delete', 'elementor-geo-popup') . '</button>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
    
    /**
     * Render edit form
     */
    private function render_edit_form() {
        $variant_id = $_GET['id'] ?? 0;
        $variant = null;
        
        if ($variant_id) {
            $variant_crud = new RW_Geo_Variant_CRUD();
            $variant = $variant_crud->get($variant_id);
        }
        
        $is_edit = !empty($variant);
        $title = $is_edit ? __('Edit Group', 'elementor-geo-popup') : __('Add New Group', 'elementor-geo-popup');
        
        echo '<h2>' . $title . '</h2>';
        
        if ($is_edit) {
            echo '<p><a href="' . admin_url('admin.php?page=geo-elementor-variants') . '">&larr; ' . __('Back to Groups', 'elementor-geo-popup') . '</a></p>';
        }
        
        echo '<form method="post" id="variant-form">';
        echo '<input type="hidden" name="variant_id" value="' . esc_attr($variant_id) . '">';
        echo '<input type="hidden" name="action" value="save_variant">';
        wp_nonce_field('rw_geo_variant_nonce', 'rw_geo_variant_nonce');
        
        echo '<table class="form-table">';
        
        // Name
        echo '<tr>';
        echo '<th scope="row"><label for="variant_name">' . __('Name', 'elementor-geo-popup') . '</label></th>';
        echo '<td><input type="text" id="variant_name" name="variant_name" value="' . esc_attr($variant->name ?? '') . '" class="regular-text" required></td>';
        echo '</tr>';
        
        // Slug
        echo '<tr>';
        echo '<th scope="row"><label for="variant_slug">' . __('Slug', 'elementor-geo-popup') . '</label></th>';
        echo '<td><input type="text" id="variant_slug" name="variant_slug" value="' . esc_attr($variant->slug ?? '') . '" class="regular-text" required></td>';
        echo '</tr>';
        
        // Type Mask
        echo '<tr>';
        echo '<th scope="row">' . __('Entity Types', 'elementor-geo-popup') . '</th>';
        echo '<td>';
        $type_mask = $variant->type_mask ?? 3;
        echo '<label><input type="checkbox" name="type_mask[]" value="' . RW_GEO_TYPE_PAGE . '" ' . checked($type_mask & RW_GEO_TYPE_PAGE, RW_GEO_TYPE_PAGE, false) . '> ' . __('Pages', 'elementor-geo-popup') . '</label><br>';
        echo '<label><input type="checkbox" name="type_mask[]" value="' . RW_GEO_TYPE_POPUP . '" ' . checked($type_mask & RW_GEO_TYPE_POPUP, RW_GEO_TYPE_POPUP, false) . '> ' . __('Popups', 'elementor-geo-popup') . '</label><br>';
        echo '<label><input type="checkbox" name="type_mask[]" value="' . RW_GEO_TYPE_SECTION . '" ' . checked($type_mask & RW_GEO_TYPE_SECTION, RW_GEO_TYPE_SECTION, false) . '> ' . __('Sections (Pro)', 'elementor-geo-popup') . '</label><br>';
        echo '<label><input type="checkbox" name="type_mask[]" value="' . RW_GEO_TYPE_WIDGET . '" ' . checked($type_mask & RW_GEO_TYPE_WIDGET, RW_GEO_TYPE_WIDGET, false) . '> ' . __('Widgets (Pro)', 'elementor-geo-popup') . '</label>';
        echo '</td>';
        echo '</tr>';
        
        // Default Page
        echo '<tr>';
        echo '<th scope="row"><label for="default_page_id">' . __('Default Page', 'elementor-geo-popup') . '</label></th>';
        echo '<td>';
        echo '<select id="default_page_id" name="default_page_id">';
        echo '<option value="">' . __('Select Page', 'elementor-geo-popup') . '</option>';
        $pages = get_pages(array('sort_column' => 'post_title'));
        foreach ($pages as $page) {
            $selected = ($variant->default_page_id ?? '') == $page->ID ? 'selected' : '';
            echo '<option value="' . $page->ID . '" ' . $selected . '>' . esc_html($page->post_title) . '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        // Default Popup
        echo '<tr>';
        echo '<th scope="row"><label for="default_popup_id">' . __('Default Popup', 'elementor-geo-popup') . '</label></th>';
        echo '<td>';
        echo '<select id="default_popup_id" name="default_popup_id">';
        echo '<option value="">' . __('Select Popup', 'elementor-geo-popup') . '</option>';
        $popups = get_posts(array(
            'post_type' => 'elementor_library',
            'meta_query' => array(
                array(
                    'key' => '_elementor_template_type',
                    'value' => 'popup'
                )
            ),
            'posts_per_page' => -1,
            'orderby' => 'title'
        ));
        foreach ($popups as $popup) {
            $selected = ($variant->default_popup_id ?? '') == $popup->ID ? 'selected' : '';
            echo '<option value="' . $popup->ID . '" ' . $selected . '>' . esc_html($popup->post_title) . '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';

        // Default Section/Widget refs (shown when types enabled)
        echo '<tr class="egp-default-section-row">';
        echo '<th scope="row"><label for="default_section_ref">' . __('Default Section ID/Template', 'elementor-geo-popup') . '</label></th>';
        echo '<td>';
        $def_section = $variant->default_section_ref ?? '';
        echo '<div style="display:grid;grid-template-columns:1fr;gap:6px;max-width:560px;">';
        echo '<div><label style="display:block;font-weight:600;margin-bottom:2px;">' . __('CSS ID or Elementor ID', 'elementor-geo-popup') . '</label>';
        echo '<input type="text" id="default_section_ref" name="default_section_ref" value="' . esc_attr($def_section) . '" placeholder="#hero-us or element ID" style="width:100%;max-width:360px;" />';
        echo '<div class="description" style="margin-top:4px;">' . __('Set Advanced > CSS ID (no #) or use element data-id.', 'elementor-geo-popup') . '</div></div>';
        echo '<div><label style="display:block;font-weight:600;margin-bottom:2px;">' . __('Or select a Section/Container template', 'elementor-geo-popup') . '</label>';
        echo '<select id="default_section_tpl" style="width:100%;max-width:360px;">';
        echo '<option value="">— ' . __('Select a template', 'elementor-geo-popup') . ' —</option>';
        $tpls = get_posts(array(
            'post_type' => 'elementor_library',
            'post_status' => array('publish','draft','private'),
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => array(array('key' => '_elementor_template_type','value' => array('section','container'),'compare' => 'IN'))
        ));
        foreach ($tpls as $p) { echo '<option value="template:' . $p->ID . '">' . esc_html($p->post_title) . '</option>'; }
        echo '</select> ';
        echo '<a href="' . esc_url(admin_url('edit.php?post_type=elementor_library&tabs_group=library&rw_open_new=1')) . '" target="_blank" class="button button-small">' . __('Create new template', 'elementor-geo-popup') . '</a>';
        echo '</div>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';

        echo '<tr class="egp-default-widget-row">';
        echo '<th scope="row"><label for="default_widget_ref">' . __('Default Widget ID/Template', 'elementor-geo-popup') . '</label></th>';
        echo '<td>';
        $def_widget = $variant->default_widget_ref ?? '';
        echo '<div style="display:grid;grid-template-columns:1fr;gap:6px;max-width:560px;">';
        echo '<div><label style="display:block;font-weight:600;margin-bottom:2px;">' . __('CSS ID or Elementor ID', 'elementor-geo-popup') . '</label>';
        echo '<input type="text" id="default_widget_ref" name="default_widget_ref" value="' . esc_attr($def_widget) . '" placeholder="#cta or element ID" style="width:100%;max-width:360px;" />';
        echo '<div class="description" style="margin-top:4px;">' . __('Set Advanced > CSS ID (no #) or use element data-id.', 'elementor-geo-popup') . '</div></div>';
        echo '<div><label style="display:block;font-weight:600;margin-bottom:2px;">' . __('Or select a Global Widget/Container template', 'elementor-geo-popup') . '</label>';
        echo '<select id="default_widget_tpl" style="width:100%;max-width:360px;">';
        echo '<option value="">— ' . __('Select a template', 'elementor-geo-popup') . ' —</option>';
        $wtpls = get_posts(array(
            'post_type' => 'elementor_library',
            'post_status' => array('publish','draft','private'),
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => array(array('key' => '_elementor_template_type','value' => array('widget','global_widget'),'compare' => 'IN'))
        ));
        foreach ($wtpls as $p) { echo '<option value="template:' . $p->ID . '">' . esc_html($p->post_title) . '</option>'; }
        echo '</select> ';
        echo '<a href="' . esc_url(admin_url('edit.php?post_type=elementor_library&tabs_group=library&rw_open_new=1')) . '" target="_blank" class="button button-small">' . __('Create new template', 'elementor-geo-popup') . '</a>';
        echo '</div>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
        
        // Options
        echo '<tr>';
        echo '<th scope="row">' . __('Options', 'elementor-geo-popup') . '</th>';
        echo '<td>';
        $options = $variant->options ?? array();
        echo '<label><input type="checkbox" name="options[soft_redirect]" value="1" ' . checked(!empty($options['soft_redirect']), true, false) . '> ' . __('Enable soft redirects (302)', 'elementor-geo-popup') . '</label><br>';
        echo '<label><input type="checkbox" name="options[show_selector]" value="1" ' . checked(!empty($options['show_selector']), true, false) . '> ' . __('Show region selector banner', 'elementor-geo-popup') . '</label><br>';
        echo '<label><input type="checkbox" name="options[respect_cookie]" value="1" ' . checked(!empty($options['respect_cookie']), true, false) . '> ' . __('Respect manual region cookie', 'elementor-geo-popup') . '</label><br>';
        echo '<label><input type="checkbox" name="options[skip_bots]" value="1" ' . checked(!empty($options['skip_bots']), true, false) . '> ' . __('Skip bots/crawlers', 'elementor-geo-popup') . '</label><br>';
        echo '<label>Cookie TTL (days): <input type="number" name="options[cookie_ttl]" value="' . esc_attr($options['cookie_ttl'] ?? 60) . '" min="1" max="365" class="small-text"></label>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="submit" id="submit" class="button button-primary" value="' . ($is_edit ? __('Update Group', 'elementor-geo-popup') : __('Add Group', 'elementor-geo-popup')) . '">';
        if ($is_edit) {
            echo ' <a href="' . admin_url('admin.php?page=geo-elementor-variants') . '" class="button">' . __('Cancel', 'elementor-geo-popup') . '</a>';
        }
        echo '</p>';
        
        echo '</form>';
        
        if ($is_edit) {
            $this->render_country_mappings($variant);
        }
    }
    
    /**
     * Render country mappings section
     */
    private function render_country_mappings($variant) {
        $mapping_crud = new RW_Geo_Mapping_CRUD();
        $mappings = $mapping_crud->get_by_variant($variant->id);
        
        echo '<hr>';
        echo '<h3>' . __('Country Mappings', 'elementor-geo-popup') . '</h3>';
        echo '<p>' . __('Configure which countries should show specific content instead of the defaults.', 'elementor-geo-popup') . '</p>';
        
        echo '<div id="country-mappings" style="margin-top:15px;">';
        
        if (!empty($mappings)) {
            foreach ($mappings as $mapping) {
                $this->render_mapping_row($mapping, $variant);
            }
        }
        
        echo '</div>';
        
        echo '<p><button type="button" class="button add-mapping" id="egp-add-mapping">' . __('Add Country Mapping', 'elementor-geo-popup') . '</button></p>';
        
        // Template for new mapping rows
        // Use a data attribute to avoid other plugins stripping script templates
        echo '<template id="mapping-template" data-template="mapping">';
        $this->render_mapping_row(null, $variant, true);
        echo '</template>';
        // Ensure visibility in case other plugins hide elements unintentionally
        echo '<style>#country-mappings{display:block!important}.mapping-row{display:block!important}.mapping-row tr{display:table-row!important}</style>';
    }
    
    /**
     * Render a single mapping row
     */
    private function render_mapping_row($mapping = null, $variant = null, $is_template = false) {
        $mapping_id = $mapping ? $mapping->id : '{{id}}';
        $country = $mapping ? $mapping->country_iso2 : '';
        $page_id = $mapping ? $mapping->page_id : '';
        $popup_id = $mapping ? $mapping->popup_id : '';
        
        $row_class = $is_template ? 'mapping-row template' : 'mapping-row';
        $row_id = $is_template ? 'mapping-template-row' : 'mapping-row-' . $mapping_id;
        
        echo '<div class="' . $row_class . '" id="' . $row_id . '">';
        echo '<h4>' . __('Country Mapping', 'elementor-geo-popup') . '</h4>';
        
        echo '<table class="form-table">';
        
        // Country
        echo '<tr>';
        echo '<th scope="row"><label>' . __('Country', 'elementor-geo-popup') . '</label></th>';
        echo '<td>';
        echo '<select name="mappings[' . $mapping_id . '][country_iso2]" class="country-select" required>';
        echo '<option value="">' . __('Select Country', 'elementor-geo-popup') . '</option>';
        
        $countries = $this->get_countries_list();
        foreach ($countries as $code => $name) {
            $selected = $country === $code ? 'selected' : '';
            echo '<option value="' . $code . '" ' . $selected . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        // Page (if enabled)
        if ($variant && ($variant->type_mask & RW_GEO_TYPE_PAGE)) {
            echo '<tr>';
            echo '<th scope="row"><label>' . __('Page', 'elementor-geo-popup') . '</label></th>';
            echo '<td>';
            echo '<select name="mappings[' . $mapping_id . '][page_id]" class="page-select">';
            echo '<option value="">' . __('Use Default', 'elementor-geo-popup') . '</option>';
            $pages = get_pages(array('sort_column' => 'post_title'));
            foreach ($pages as $page) {
                $selected = $page_id == $page->ID ? 'selected' : '';
                echo '<option value="' . $page->ID . '" ' . $selected . '>' . esc_html($page->post_title) . '</option>';
            }
            echo '</select>';
            echo '</td>';
            echo '</tr>';
        }
        
        // Popup (if enabled)
        if ($variant && ($variant->type_mask & RW_GEO_TYPE_POPUP)) {
            echo '<tr>';
            echo '<th scope="row"><label>' . __('Popup', 'elementor-geo-popup') . '</label></th>';
            echo '<td>';
            echo '<select name="mappings[' . $mapping_id . '][popup_id]" class="popup-select">';
            echo '<option value="">' . __('Use Default', 'elementor-geo-popup') . '</option>';
            $popups = get_posts(array(
                'post_type' => 'elementor_library',
                'meta_query' => array(
                    array(
                        'key' => '_elementor_template_type',
                        'value' => 'popup'
                    )
                ),
                'posts_per_page' => -1,
                'orderby' => 'title'
            ));
            foreach ($popups as $popup) {
                $selected = $popup_id == $popup->ID ? 'selected' : '';
                echo '<option value="' . $popup->ID . '" ' . $selected . '>' . esc_html($popup->post_title) . '</option>';
            }
            echo '</select>';
            echo '</td>';
            echo '</tr>';
        }

        // Section (if enabled) - by CSS ID or Elementor data-id
        if ($variant && ($variant->type_mask & RW_GEO_TYPE_SECTION)) {
            $section_ref = $mapping ? ($mapping->section_ref ?? '') : '';
            echo '<tr class="section-ref-row">';
            echo '<th scope="row"><label>' . __('Section ID', 'elementor-geo-popup') . '</label></th>';
            echo '<td>';
            echo '# <input type="text" name="mappings[' . $mapping_id . '][section_ref]" class="section-ref" value="' . esc_attr($section_ref) . '" placeholder="hero-us or Elementor data-id" style="min-width:260px;" />';
            echo '<p class="description" style="margin:4px 0 0 0;">' . __('Set this in Elementor > Advanced > CSS ID (without #), or use the element\'s data-id from the editor.', 'elementor-geo-popup') . '</p>';
            echo '</td>';
            echo '</tr>';
        }

        // Widget (if enabled) - by CSS ID or Elementor data-id
        if ($variant && ($variant->type_mask & RW_GEO_TYPE_WIDGET)) {
            $widget_ref = $mapping ? ($mapping->widget_ref ?? '') : '';
            echo '<tr class="widget-ref-row">';
            echo '<th scope="row"><label>' . __('Widget ID', 'elementor-geo-popup') . '</label></th>';
            echo '<td>';
            echo '# <input type="text" name="mappings[' . $mapping_id . '][widget_ref]" class="widget-ref" value="' . esc_attr($widget_ref) . '" placeholder="cta-button or Elementor data-id" style="min-width:260px;" />';
            echo '<p class="description" style="margin:4px 0 0 0;">' . __('Set this in Elementor > Advanced > CSS ID (without #), or use the element\'s data-id from the editor.', 'elementor-geo-popup') . '</p>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        
        echo '<p>';
        echo '<button type="button" class="button button-small save-mapping" data-id="' . $mapping_id . '">' . __('Save Mapping', 'elementor-geo-popup') . '</button> ';
        echo '<button type="button" class="button button-small button-link-delete delete-mapping" data-id="' . $mapping_id . '">' . __('Delete', 'elementor-geo-popup') . '</button>';
        echo '</p>';
        
        echo '</div>';
    }
    
    /**
     * Get countries list
     */
    private function get_countries_list() {
        // Load from bundled ISO-3166 list if available
        $json_path = dirname(__DIR__) . '/assets/data/countries.json';
        if (file_exists($json_path)) {
            $contents = file_get_contents($json_path);
            $decoded = json_decode($contents, true);
            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }
        }

        // Fallback minimal list
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
     * AJAX: Search countries (predictive)
     */
    public function ajax_search_countries() {
        check_ajax_referer('rw_geo_variants_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }

        $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $q_upper = strtoupper($q);
        $q_lower = strtolower($q);

        $countries = $this->get_countries_list();
        $results = array();

        foreach ($countries as $code => $name) {
            $code_str = strtoupper($code);
            $name_str = (string) $name;
            if ($q === '' || strpos($code_str, $q_upper) !== false || strpos(strtolower($name_str), $q_lower) !== false) {
                $results[] = array('code' => $code_str, 'name' => $name_str);
            }
        }

        // Sort by name for better UX
        usort($results, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        wp_send_json_success($results);
    }
    
    /**
     * AJAX: Save variant
     */
    public function ajax_save_variant() {
        check_ajax_referer('rw_geo_variants_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $variant_id = intval($_POST['variant_id'] ?? 0);
        $name = sanitize_text_field($_POST['variant_name'] ?? '');
        $slug = sanitize_key($_POST['variant_slug'] ?? '');
        
        if (empty($name) || empty($slug)) {
            wp_send_json_error(__('Name and slug are required', 'elementor-geo-popup'));
        }
        
        // Calculate type mask
        $type_mask = 0;
        $types = $_POST['type_mask'] ?? array();
        foreach ($types as $type) {
            $type_mask |= intval($type);
        }
        
        $data = array(
            'name' => $name,
            'slug' => $slug,
            'type_mask' => $type_mask,
            'default_page_id' => intval($_POST['default_page_id'] ?? 0) ?: null,
            'default_popup_id' => intval($_POST['default_popup_id'] ?? 0) ?: null,
            'options' => array(
                'soft_redirect' => !empty($_POST['options']['soft_redirect']),
                'show_selector' => !empty($_POST['options']['show_selector']),
                'respect_cookie' => !empty($_POST['options']['respect_cookie']),
                'skip_bots' => !empty($_POST['options']['skip_bots']),
                'cookie_ttl' => intval($_POST['options']['cookie_ttl'] ?? 60)
            )
        );
        
        $variant_crud = new RW_Geo_Variant_CRUD();
        
        if ($variant_id) {
            $result = $variant_crud->update($variant_id, $data);
        } else {
            $result = $variant_crud->create($data);
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => $variant_id ? __('Group updated successfully', 'elementor-geo-popup') : __('Group created successfully', 'elementor-geo-popup'),
            'variant_id' => $variant_id ?: $result
        ));
    }
    
    /**
     * AJAX: Delete variant
     */
    public function ajax_delete_variant() {
        check_ajax_referer('rw_geo_variants_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $variant_id = intval($_POST['variant_id'] ?? 0);
        
        if (!$variant_id) {
            wp_send_json_error(__('Invalid group ID', 'elementor-geo-popup'));
        }
        
        $variant_crud = new RW_Geo_Variant_CRUD();
        $result = $variant_crud->delete($variant_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('Group deleted successfully', 'elementor-geo-popup'));
    }
    
    /**
     * AJAX: Save mapping
     */
    public function ajax_save_mapping() {
        check_ajax_referer('rw_geo_variants_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $variant_id = intval($_POST['variant_id'] ?? 0);
        $country_iso2 = strtoupper($_POST['country_iso2'] ?? '');
        $page_id = intval($_POST['page_id'] ?? 0) ?: null;
        $popup_id = intval($_POST['popup_id'] ?? 0) ?: null;
        
        if (!$variant_id || !$country_iso2) {
            wp_send_json_error(__('Group ID and country are required', 'elementor-geo-popup'));
        }
        
        // Prevent conflicts with existing Rules that target the same Page/Popup
        if ($page_id) {
            $conflict = get_posts(array(
                'post_type' => 'geo_rule',
                'post_status' => 'any',
                'meta_query' => array(
                    array('key' => 'egp_target_type', 'value' => 'page'),
                    array('key' => 'egp_target_id', 'value' => (string) $page_id)
                ),
                'fields' => 'ids',
                'posts_per_page' => 1
            ));
            if (!empty($conflict)) {
                wp_send_json_error(__('Conflict: This Page is already targeted by a Rule. Remove the Rule or choose a different target.', 'elementor-geo-popup'));
            }
        }

        if ($popup_id) {
            $conflict = get_posts(array(
                'post_type' => 'geo_rule',
                'post_status' => 'any',
                'meta_query' => array(
                    array('key' => 'egp_target_type', 'value' => 'popup'),
                    array('key' => 'egp_target_id', 'value' => (string) $popup_id)
                ),
                'fields' => 'ids',
                'posts_per_page' => 1
            ));
            if (!empty($conflict)) {
                wp_send_json_error(__('Conflict: This Popup is already targeted by a Rule. Remove the Rule or choose a different target.', 'elementor-geo-popup'));
            }
        }
        
        $data = array(
            'variant_id' => $variant_id,
            'country_iso2' => $country_iso2,
            'page_id' => $page_id,
            'popup_id' => $popup_id
        );
        
        $mapping_crud = new RW_Geo_Mapping_CRUD();
        
        // Check if mapping exists
        $existing = $mapping_crud->get_by_variant_country($variant_id, $country_iso2);
        
        if ($existing) {
            $result = $mapping_crud->update($existing->id, $data);
        } else {
            $result = $mapping_crud->create($data);
        }

        // Keep Rules in sync: if popup_id is set, ensure corresponding rule exists/updates
        if (!is_wp_error($result) && $popup_id) {
            if (class_exists('EGP_Geo_Rules')) {
                $rules = EGP_Geo_Rules::get_instance();
                if (method_exists($rules, 'save_or_update_rule')) {
                    $rules->save_or_update_rule(
                        'popup',
                        (string) $popup_id,
                        array($country_iso2),
                        50,
                        true,
                        'groups',
                        get_the_title($popup_id)
                    );
                }
            }
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => __('Mapping saved successfully', 'elementor-geo-popup'),
            'mapping_id' => $existing ? $existing->id : $result
        ));
    }
    
    /**
     * AJAX: Delete mapping
     */
    public function ajax_delete_mapping() {
        check_ajax_referer('rw_geo_variants_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $mapping_id = intval($_POST['mapping_id'] ?? 0);
        
        if (!$mapping_id) {
            wp_send_json_error(__('Invalid mapping ID', 'elementor-geo-popup'));
        }
        
        $mapping_crud = new RW_Geo_Mapping_CRUD();
        $result = $mapping_crud->delete($mapping_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('Mapping deleted successfully', 'elementor-geo-popup'));
    }
    
    /**
     * AJAX: Get pages
     */
    public function ajax_get_pages() {
        check_ajax_referer('rw_geo_variants_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $pages = get_pages(array('sort_column' => 'post_title'));
        $data = array();
        
        foreach ($pages as $page) {
            $data[] = array(
                'id' => $page->ID,
                'title' => $page->post_title
            );
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX: Get popups
     */
    public function ajax_get_popups() {
        check_ajax_referer('rw_geo_variants_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $popups = get_posts(array(
            'post_type' => 'elementor_library',
            'meta_query' => array(
                array(
                    'key' => '_elementor_template_type',
                    'value' => 'popup'
                )
            ),
            'posts_per_page' => -1,
            'orderby' => 'title'
        ));
        
        $data = array();
        foreach ($popups as $popup) {
            $data[] = array(
                'id' => $popup->ID,
                'title' => $popup->post_title
            );
        }
        
        wp_send_json_success($data);
    }
}

// Initialize the admin interface
RW_Geo_Variant_Groups_Admin::get_instance();
