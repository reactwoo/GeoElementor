<?php
/**
 * Unified Geo Content Dashboard
 * 
 * Shows both Elementor templates (with geo) and element visibility rules
 * 
 * @package GeoElementor
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class EGP_Geo_Content_Dashboard {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu'), 25);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function add_menu() {
        add_submenu_page(
            'geo-elementor',
            __('Geo Content', 'elementor-geo-popup'),
            __('Geo Content', 'elementor-geo-popup'),
            'edit_posts',
            'geo-content',
            array($this, 'render_page')
        );
    }
    
    public function enqueue_scripts($hook) {
        if ($hook !== 'geo-elementor_page_geo-content') {
            return;
        }
        
        wp_enqueue_style('egp-content-dashboard', plugin_dir_url(dirname(__FILE__)) . 'assets/css/content-dashboard.css', array(), '1.0.0');
    }
    
    public function render_page() {
        // Get geo-enabled Elementor templates
        $geo_templates = get_posts(array(
            'post_type' => 'elementor_library',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array('key' => 'egp_geo_enabled', 'value' => 'yes')
            ),
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        // Get element visibility rules
        $element_rules = get_posts(array(
            'post_type' => 'geo_rule',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        ?>
        <div class="wrap egp-settings egp-content-dashboard">
			<?php if ( class_exists( 'EGP_Admin_Menu' ) ) { EGP_Admin_Menu::render_page_header( esc_html__( 'Geo Content Management', 'elementor-geo-popup' ), 'geo-content' ); } ?>

			<div class="notice notice-info" style="margin:14px 0;">
				<p>
					<?php
					esc_html_e(
						'Baseline (Free) routing and geo engine live in ReactWoo Geo Core. GeoElementor Pro extends this with advanced variant groups and element-level targeting.',
						'elementor-geo-popup'
					);
					?>
				</p>
				<p>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-usage' ) ); ?>"><?php esc_html_e( 'Geo Core Free Routing Guide', 'elementor-geo-popup' ); ?></a>
					<a class="button" href="<?php echo esc_url( EGP_Admin_Menu::get_sync_rules_url() ); ?>"><?php esc_html_e( 'Run Sync Now', 'elementor-geo-popup' ); ?></a>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=geo-elementor-variants' ) ); ?>"><?php esc_html_e( 'Manage Variant Groups', 'elementor-geo-popup' ); ?></a>
				</p>
			</div>
            
            <div class="egp-help-box">
                <h3><span class="dashicons dashicons-info" style="vertical-align: middle;"></span> <?php _e('How It Works', 'elementor-geo-popup'); ?></h3>
                <p class="description">
                    <?php _e('Geo Core (free) handles basic Elementor page/popup geo visibility plus page-level server-side routing (1 default + 1 country variant). GeoElementor extends this with advanced multi-variant routing, element-level rules, groups, and analytics.', 'elementor-geo-popup'); ?>
                </p>
                <div class="egp-workflow-guide">
                    <div class="egp-workflow-item">
                        <strong><?php _e('Reusable Content:', 'elementor-geo-popup'); ?></strong>
                        <p><?php _e('Create templates in Elementor → Enable Geo Targeting → Insert on multiple pages', 'elementor-geo-popup'); ?></p>
                        <a href="<?php echo admin_url('edit.php?post_type=elementor_library'); ?>" class="button">
                            <?php _e('Manage in Elementor Templates', 'elementor-geo-popup'); ?>
                        </a>
                    </div>
                    <div class="egp-workflow-item">
                        <strong><?php _e('Element Visibility:', 'elementor-geo-popup'); ?></strong>
                        <p><?php _e('Open page in Elementor → Click element → Advanced → Geo Targeting', 'elementor-geo-popup'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Reusable Geo Content (Elementor Templates) -->
            <h2><span class="dashicons dashicons-location-alt" style="vertical-align: middle;"></span> <?php _e('Reusable Geo Content', 'elementor-geo-popup'); ?></h2>
            <p class="description">
                <?php _e('Elementor templates with geo-targeting enabled. Manage these in Elementor → Templates.', 'elementor-geo-popup'); ?>
            </p>
            
            <?php if (empty($geo_templates)): ?>
                <div class="egp-empty-state">
                    <div class="egp-empty-icon"><span class="dashicons dashicons-location-alt" style="font-size: 48px;"></span></div>
                    <h3><?php _e('No geo templates yet', 'elementor-geo-popup'); ?></h3>
                    <p><?php _e('Create a template in Elementor and enable Geo Targeting in its settings.', 'elementor-geo-popup'); ?></p>
                    <a href="<?php echo admin_url('post-new.php?post_type=elementor_library&elementor_library_type=page'); ?>" class="button button-primary">
                        <?php _e('Create Template in Elementor', 'elementor-geo-popup'); ?>
                    </a>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'elementor-geo-popup'); ?></th>
                            <th><?php _e('Elementor Type', 'elementor-geo-popup'); ?></th>
                            <th><?php _e('Countries', 'elementor-geo-popup'); ?></th>
                            <th><?php _e('Usage', 'elementor-geo-popup'); ?></th>
                            <th><?php _e('Actions', 'elementor-geo-popup'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($geo_templates as $template): 
                            $template_type = get_post_meta($template->ID, '_elementor_template_type', true);
                            $page_settings = get_post_meta($template->ID, '_elementor_page_settings', true);
                            $countries = isset($page_settings['egp_countries']) ? $page_settings['egp_countries'] : array();
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html($template->post_title); ?></strong></td>
                                <td>
                                    <span class="egp-type-badge egp-type-<?php echo esc_attr($template_type); ?>">
                                        <?php echo esc_html(ucfirst($template_type)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if (is_array($countries) && !empty($countries)) {
                                        echo esc_html(implode(', ', array_slice($countries, 0, 5)));
                                        if (count($countries) > 5) {
                                            echo ' <span class="egp-more">+' . (count($countries) - 5) . '</span>';
                                        }
                                    } else {
                                        echo '<span class="egp-no-countries">' . __('None', 'elementor-geo-popup') . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $usage = $this->count_template_usage($template->ID);
                                    echo intval($usage) . ' ' . __('pages', 'elementor-geo-popup'); 
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('post.php?post=' . $template->ID . '&action=elementor'); ?>" 
                                       class="button button-primary button-small">
                                        <?php _e('Edit in Elementor', 'elementor-geo-popup'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <br><br>
            
            <!-- Element Visibility Rules -->
            <h2><span class="dashicons dashicons-visibility" style="vertical-align: middle;"></span> <?php _e('Element Visibility Rules', 'elementor-geo-popup'); ?></h2>
            <p class="description">
                <?php _e('Page-specific rules that hide/show elements based on visitor country.', 'elementor-geo-popup'); ?>
            </p>
            
            <?php if (empty($element_rules)): ?>
                <div class="egp-empty-state">
                    <div class="egp-empty-icon"><span class="dashicons dashicons-visibility" style="font-size: 48px;"></span></div>
                    <h3><?php _e('No element rules yet', 'elementor-geo-popup'); ?></h3>
                    <p><?php _e('Open a page in Elementor, click any element, then go to Advanced → Geo Targeting.', 'elementor-geo-popup'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'elementor-geo-popup'); ?></th>
                            <th><?php _e('Type', 'elementor-geo-popup'); ?></th>
                            <th><?php _e('Countries', 'elementor-geo-popup'); ?></th>
                            <th><?php _e('Status', 'elementor-geo-popup'); ?></th>
                            <th><?php _e('Actions', 'elementor-geo-popup'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($element_rules as $rule): 
                            $type = get_post_meta($rule->ID, 'egp_target_type', true);
                            $countries = get_post_meta($rule->ID, 'egp_countries', true);
                            $active = get_post_meta($rule->ID, 'egp_active', true);
                            $document_id = get_post_meta($rule->ID, 'egp_elementor_document_id', true);
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html($rule->post_title); ?></strong></td>
                                <td><?php echo esc_html(ucfirst($type)); ?></td>
                                <td>
                                    <?php 
                                    if (is_array($countries) && !empty($countries)) {
                                        echo esc_html(implode(', ', $countries));
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($active == '1'): ?>
                                        <span class="egp-status-active">● Active</span>
                                    <?php else: ?>
                                        <span class="egp-status-inactive">○ Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($document_id): ?>
                                        <a href="<?php echo admin_url('post.php?post=' . $document_id . '&action=elementor'); ?>" 
                                           class="button button-small">
                                            <?php _e('Edit in Elementor', 'elementor-geo-popup'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function count_template_usage($template_id) {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT post_id)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_elementor_data'
            AND meta_value LIKE %s
        ", '%"template_id":"' . $template_id . '"%'));
        return intval($count);
    }
}

// Initialize
EGP_Geo_Content_Dashboard::get_instance();

