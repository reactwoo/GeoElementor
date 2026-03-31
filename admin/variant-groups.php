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
        wp_enqueue_style('dashicons');
        
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
            (function_exists('filemtime') ? @filemtime(dirname(__DIR__) . '/assets/js/variants-admin.js') : EGP_VERSION),
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
        if ( class_exists( 'EGP_Admin_Menu' ) ) {
            $routing_notice  = '<div class="notice notice-info egp-routing-context-notice">';
            $routing_notice .= '<p>' . esc_html__( 'Routing ownership: Geo Core controls free Master/Secondary routing, while this screen controls Pro groups layered on top for country-first matching.', 'elementor-geo-popup' ) . '</p>';
            $routing_notice .= '<p>';
            $routing_notice .= '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=rwgc-usage' ) ) . '">' . esc_html__( 'Geo Core Free Routing Guide', 'elementor-geo-popup' ) . '</a> ';
            $routing_notice .= '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=geo-elementor-variants' ) ) . '">' . esc_html__( 'You are here: Variant Groups', 'elementor-geo-popup' ) . '</a>';
            $routing_notice .= '</p></div>';
            EGP_Admin_Menu::render_page_header( esc_html__( 'Groups', 'elementor-geo-popup' ), 'geo-elementor-variants', $routing_notice );
        }
        echo '<div class="egp-section-card">';
        echo '<p style="margin:0 0 8px 0;">' . __('Manage Pro Groups that map content (Pages/Popups/Sections/Widgets) to countries for a selected Master page.', 'elementor-geo-popup') . '</p>';
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
        echo '<th scope="col" class="manage-column column-actions">' . esc_html__( 'Actions', 'elementor-geo-popup' ) . '</th>';
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
            $edit_url = admin_url( 'admin.php?page=geo-elementor-variants&action=edit&id=' . (int) $variant->id );
            echo '<td class="column-actions egp-rule-actions-cell">';
            echo '<span class="egp-rule-actions" role="group" aria-label="' . esc_attr__( 'Group actions', 'elementor-geo-popup' ) . '">';
            echo '<a href="' . esc_url( $edit_url ) . '" class="button button-small egp-icon-btn" title="' . esc_attr__( 'Edit', 'elementor-geo-popup' ) . '"><span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Edit', 'elementor-geo-popup' ) . '</span></a>';
            echo '<button type="button" class="button button-small button-link-delete egp-icon-btn delete-variant" data-id="' . esc_attr( $variant->id ) . '" title="' . esc_attr__( 'Delete', 'elementor-geo-popup' ) . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Delete', 'elementor-geo-popup' ) . '</span></button>';
            echo '</span></td>';
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
        
        // Master context
        echo '<tr>';
        echo '<th scope="row"><label for="master_page_id">' . __('Master Page', 'elementor-geo-popup') . '</label></th>';
        echo '<td>';
        echo '<select id="master_page_id" name="master_page_id">';
        echo '<option value="">' . __('Select Master Page', 'elementor-geo-popup') . '</option>';
        $pages = get_pages(array('sort_column' => 'post_title'));
        foreach ($pages as $page) {
            $selected = ($variant->master_page_id ?? '') == $page->ID ? 'selected' : '';
            echo '<option value="' . $page->ID . '" ' . $selected . '>' . esc_html($page->post_title) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Choose the Master page this Pro group should manage.', 'elementor-geo-popup') . '</p>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . __('Runtime Status', 'elementor-geo-popup') . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="is_active" value="1" ' . checked((int) ($variant->is_active ?? 1), 1, false) . '> ' . __('Active in runtime', 'elementor-geo-popup') . '</label><br>';
        echo '<label>' . __('Priority', 'elementor-geo-popup') . ' <input type="number" name="priority" value="' . esc_attr($variant->priority ?? 50) . '" min="1" max="1000" class="small-text"></label>';
        echo '<p class="description">' . __('Lower numbers run first when multiple groups target the same Master page.', 'elementor-geo-popup') . '</p>';
        echo '</td>';
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
        
        // Default Page (show only when Page type enabled)
        if (($type_mask & RW_GEO_TYPE_PAGE)) {
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
        }
        
        // Default Popup (show only when Popup type enabled)
        if (($type_mask & RW_GEO_TYPE_POPUP)) {
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
        }

        // Default Section/Widget refs (shown when types enabled)
        if (($type_mask & RW_GEO_TYPE_SECTION)) { echo '<tr class="egp-default-section-row">'; } else { echo '<tr class="egp-default-section-row" style="display:none">'; }
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
        echo '<a href="#" id="egp_default_section_edit_tpl">' . __('Edit in Elementor', 'elementor-geo-popup') . '</a>';
        echo '<div class="description" style="margin-top:4px;"><a href="' . esc_url(admin_url('edit.php?post_type=elementor_library&tabs_group=library&rw_open_new=1')) . '" target="_blank">' . __('Create new template in Elementor', 'elementor-geo-popup') . '</a></div>';
        echo '</div>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';

        if (($type_mask & RW_GEO_TYPE_WIDGET)) { echo '<tr class="egp-default-widget-row">'; } else { echo '<tr class="egp-default-widget-row" style="display:none">'; }
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
        echo '<a href="#" id="egp_default_widget_edit_tpl">' . __('Edit in Elementor', 'elementor-geo-popup') . '</a>';
        echo '<div class="description" style="margin-top:4px;"><a href="' . esc_url(admin_url('edit.php?post_type=elementor_library&tabs_group=library&rw_open_new=1')) . '" target="_blank">' . __('Create new template in Elementor', 'elementor-geo-popup') . '</a></div>';
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
        echo '<hr style="margin:10px 0;">';
        echo '<strong>' . __('A/B Experiment (optional)', 'elementor-geo-popup') . '</strong><br>';
        echo '<label><input type="checkbox" name="options[ab_test][enabled]" value="1" ' . checked(!empty($options['ab_test']['enabled']), true, false) . '> ' . __('Enable weighted split inside this group', 'elementor-geo-popup') . '</label><br>';
        echo '<label>' . __('Experiment Key', 'elementor-geo-popup') . ': <input type="text" name="options[ab_test][experiment_key]" value="' . esc_attr($options['ab_test']['experiment_key'] ?? '') . '" class="regular-text"></label><br>';
        echo '<label>' . __('Goal Event', 'elementor-geo-popup') . ': <input type="text" name="options[ab_test][goal_event]" value="' . esc_attr($options['ab_test']['goal_event'] ?? 'conversion') . '" class="regular-text"></label>';
        echo '<p class="description">' . __('Define buckets in options[ab_test][buckets] via integration/custom UI; runtime assignment is sticky by cookie.', 'elementor-geo-popup') . '</p>';
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
        
        // Countries (Multi-select)
        echo '<tr>';
        echo '<th scope="row"><label>' . __('Countries', 'elementor-geo-popup') . '</label></th>';
        echo '<td>';
        echo '<span class="description" style="display: block; margin-bottom: 5px;">' . __('Hold Ctrl (Cmd on Mac) to select multiple countries', 'elementor-geo-popup') . '</span>';
        
        // Get existing countries (convert from old single country or use array)
        $selected_countries = array();
        if ($mapping) {
            if (isset($mapping->countries) && is_array($mapping->countries)) {
                $selected_countries = $mapping->countries;
            } elseif (isset($mapping->country_iso2) && !empty($mapping->country_iso2)) {
                $selected_countries = array($mapping->country_iso2);
            }
        }
        
        echo '<select name="mappings[' . $mapping_id . '][countries][]" class="country-select" multiple="multiple" size="8" style="width: 100%; max-width: 400px;" required>';
        
        $countries = $this->get_countries_list();
        foreach ($countries as $code => $name) {
            // Normalize in case countries.json provides objects/arrays
            if (is_array($name)) {
                if (isset($name['name'])) { $name = $name['name']; }
                elseif (isset($name['title'])) { $name = $name['title']; }
                else { $name = (string) reset($name); }
            }
            $name = (string) $name;
            $selected = in_array($code, $selected_countries, true) ? 'selected' : '';
            echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_html($code . ' - ' . $name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description" style="margin-top: 5px;">' . __('Select one or more countries that should see this content variant', 'elementor-geo-popup') . '</p>';
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

        // Section reference (only show for Section types)
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

        // Widget reference (only show for Widget type)
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
        
        echo '<p class="egp-mapping-row-actions"><span class="egp-rule-actions" role="group" aria-label="' . esc_attr__( 'Mapping actions', 'elementor-geo-popup' ) . '">';
        echo '<button type="button" class="button button-small save-mapping" data-id="' . esc_attr( $mapping_id ) . '">' . esc_html__( 'Save Mapping', 'elementor-geo-popup' ) . '</button> ';
        echo '<button type="button" class="button button-small button-link-delete egp-icon-btn delete-mapping" data-id="' . esc_attr( $mapping_id ) . '" title="' . esc_attr__( 'Delete', 'elementor-geo-popup' ) . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Delete', 'elementor-geo-popup' ) . '</span></button>';
        echo '</span></p>';
        
        echo '</div>';
    }
    
    /**
     * Get countries list (canonical: assets/data/countries.json via egp_get_country_options).
     */
    private function get_countries_list() {
        return function_exists( 'egp_get_country_options' ) ? egp_get_country_options() : array();
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
            // Normalize name if provided as array/object
            if (is_array($name)) {
                if (isset($name['name'])) { $name = $name['name']; }
                elseif (isset($name['title'])) { $name = $name['title']; }
                else { $name = (string) reset($name); }
            }
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
            'master_page_id' => intval($_POST['master_page_id'] ?? 0) ?: null,
            'is_active' => !empty($_POST['is_active']) ? 1 : 0,
            'priority' => intval($_POST['priority'] ?? 50),
            'default_page_id' => intval($_POST['default_page_id'] ?? 0) ?: null,
            'default_popup_id' => intval($_POST['default_popup_id'] ?? 0) ?: null,
            'options' => array(
                'soft_redirect' => !empty($_POST['options']['soft_redirect']),
                'show_selector' => !empty($_POST['options']['show_selector']),
                'respect_cookie' => !empty($_POST['options']['respect_cookie']),
                'skip_bots' => !empty($_POST['options']['skip_bots']),
                'cookie_ttl' => intval($_POST['options']['cookie_ttl'] ?? 60),
                'ab_test' => array(
                    'enabled' => !empty($_POST['options']['ab_test']['enabled']),
                    'experiment_key' => sanitize_key($_POST['options']['ab_test']['experiment_key'] ?? ''),
                    'goal_event' => sanitize_key($_POST['options']['ab_test']['goal_event'] ?? 'conversion'),
                    'buckets' => array(),
                ),
            )
        );

        // Validation: one active Pro routing mode per master.
        if (!empty($data['is_active']) && !empty($data['master_page_id'])) {
            $variant_crud_check = new RW_Geo_Variant_CRUD();
            $for_master = $variant_crud_check->get_active_by_master((int) $data['master_page_id']);
            foreach ($for_master as $existing_variant) {
                if ((int) $existing_variant->id !== (int) $variant_id) {
                    wp_send_json_error(__('Another active group already owns this Master page. Disable it first or choose a different Master.', 'elementor-geo-popup'));
                }
            }
        }
        
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
        
        // Handle both old (country_iso2) and new (countries array) formats
        $countries = array();
        if (isset($_POST['countries']) && is_array($_POST['countries'])) {
            $countries = array_map('sanitize_text_field', $_POST['countries']);
            // Normalize values to ISO2 codes
            $normalized = array();
            foreach ($countries as $raw) {
                $raw = trim((string) $raw);
                if ($raw === '') { continue; }
                // If option text like "US - United States" was accidentally posted
                if (preg_match('/^([A-Za-z]{2})\b/', $raw, $m)) {
                    $normalized[] = strtoupper($m[1]);
                    continue;
                }
                // If already a two-letter code
                if (preg_match('/^[A-Za-z]{2}$/', $raw)) {
                    $normalized[] = strtoupper($raw);
                    continue;
                }
                // Otherwise skip invalid values (e.g., numeric like 108)
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[EGP Debug] ajax_save_mapping: Skipping invalid country value: ' . $raw);
                }
            }
            // De-duplicate
            $countries = array_values(array_unique($normalized));
        } elseif (isset($_POST['country_iso2']) && !empty($_POST['country_iso2'])) {
            $countries = array(strtoupper(sanitize_text_field($_POST['country_iso2'])));
        }
        
        $page_id = intval($_POST['page_id'] ?? 0) ?: null;
        $popup_id = intval($_POST['popup_id'] ?? 0) ?: null;
        $section_ref = sanitize_text_field($_POST['section_ref'] ?? '');
        $widget_ref = sanitize_text_field($_POST['widget_ref'] ?? '');
        
        // Enhanced validation with detailed error messages
        if (!$variant_id) {
            error_log('[EGP Debug] ajax_save_mapping: Missing variant_id');
            wp_send_json_error(__('Group ID is required', 'elementor-geo-popup'));
        }
        
        if (empty($countries)) {
            error_log('[EGP Debug] ajax_save_mapping: Empty countries array. POST data: ' . print_r($_POST, true));
            wp_send_json_error(__('At least one country is required', 'elementor-geo-popup'));
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
        
        // Ensure we have at least one country before proceeding
        if (!isset($countries[0])) {
            error_log('[EGP Debug] ajax_save_mapping: Countries array has no elements');
            wp_send_json_error(__('Invalid country data', 'elementor-geo-popup'));
        }
        
        // Wrap database operations in try-catch to prevent 500 errors
        try {
            if (!class_exists('RW_Geo_Mapping_CRUD')) {
                error_log('[EGP Debug] ajax_save_mapping: RW_Geo_Mapping_CRUD class not found');
                wp_send_json_error(__('Mapping database handler not available', 'elementor-geo-popup'));
            }
            
            $mapping_crud = new RW_Geo_Mapping_CRUD();
            $saved_count = 0;
            $errors = array();
            
            // Loop through each country and create/update a mapping for each
            foreach ($countries as $country_code) {
                $data = array(
                    'variant_id' => $variant_id,
                    'country_iso2' => $country_code,
                    'page_id' => $page_id,
                    'popup_id' => $popup_id,
                    'section_ref' => $section_ref,
                    'widget_ref' => $widget_ref
                );
                
                // Check if mapping exists for this country
                $existing = $mapping_crud->get_by_variant_country($variant_id, $country_code);
                
                if ($existing) {
                    $result = $mapping_crud->update($existing->id, $data);
                } else {
                    $result = $mapping_crud->create($data);
                }
                
                if (is_wp_error($result)) {
                    $errors[] = $country_code . ': ' . $result->get_error_message();
                } else {
                    $saved_count++;
                }
            }
            
            // If all saves failed, return error
            if ($saved_count === 0 && !empty($errors)) {
                error_log('[EGP Debug] ajax_save_mapping: All saves failed - ' . implode('; ', $errors));
                wp_send_json_error(__('Failed to save mappings: ', 'elementor-geo-popup') . implode('; ', array_slice($errors, 0, 3)));
            }
            
            // For backwards compatibility, use the first country's result
            $result = $saved_count > 0 ? true : new WP_Error('save_failed', 'No mappings saved');
            
        } catch (Exception $e) {
            error_log('[EGP Debug] ajax_save_mapping: Exception - ' . $e->getMessage());
            wp_send_json_error(__('Database error: ', 'elementor-geo-popup') . $e->getMessage());
        }

        // Keep Rules in sync: if popup_id is set, ensure corresponding rule exists/updates
        if (!is_wp_error($result) && $popup_id && $saved_count > 0) {
            if (class_exists('EGP_Geo_Rules')) {
                $rules = EGP_Geo_Rules::get_instance();
                if (method_exists($rules, 'save_or_update_rule')) {
                    $rules->save_or_update_rule(
                        'popup',
                        (string) $popup_id,
                        $countries, // Use full countries array
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
        
        $message = sprintf(
            _n(
                'Mapping saved successfully for %d country',
                'Mappings saved successfully for %d countries',
                $saved_count,
                'elementor-geo-popup'
            ),
            $saved_count
        );
        
        if (!empty($errors)) {
            $message .= '. ' . sprintf(
                _n(
                    'Warning: %d country failed',
                    'Warning: %d countries failed',
                    count($errors),
                    'elementor-geo-popup'
                ),
                count($errors)
            );
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'saved_count' => $saved_count,
            'failed_count' => count($errors)
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
