<?php
/**
 * Time Targeting Add-On
 *
 * Target Elementor content by day/time windows.
 *
 * @package ElementorGeoPopup
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once EGP_PLUGIN_DIR . 'includes/addon-base.php';

class EGP_Time_Targeting_Addon extends EGP_Base_Addon {
    protected function get_addon_id() {
        return 'time-targeting';
    }

    protected function get_addon_data() {
        return array(
            'id' => 'time-targeting',
            'name' => 'Time-Based Targeting',
            'description' => 'Target content based on time, timezone, and business hours',
            'version' => '1.0.0',
            'author' => 'ReactWoo',
            'author_uri' => 'https://reactwoo.com',
            'plugin_uri' => 'https://reactwoo.com',
            'requires' => '1.0.0',
            'tested' => '1.0.1',
            'file' => 'time-targeting/time-targeting.php',
            'class' => 'EGP_Time_Targeting_Addon',
            'category' => 'temporal',
            'tags' => array('time', 'timezone', 'business-hours'),
            'screenshot' => '',
            'icon' => 'eicon-clock',
            'premium' => false,
            'status' => 'available',
        );
    }

    protected function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Server-side visibility filters (reliable, no frontend race conditions).
        add_action('elementor/frontend/widget/before_render', array($this, 'apply_time_targeting'));
        add_action('elementor/frontend/container/before_render', array($this, 'apply_time_targeting'));
        add_action('elementor/frontend/section/before_render', array($this, 'apply_time_targeting'));
        add_action('elementor/frontend/column/before_render', array($this, 'apply_time_targeting'));
        add_action('wp_footer', array($this, 'render_debug_badge'));
    }

    protected function init_elementor_integration() {
        add_action('elementor/element/common/_section_style/after_section_end', array($this, 'register_time_controls'), 20);
    }

    public function add_admin_menu() {
        add_submenu_page(
            'elementor-geo-popup',
            __('Time Targeting Settings', 'elementor-geo-popup'),
            __('Time Settings', 'elementor-geo-popup'),
            'manage_options',
            'egp-time-settings',
            array($this, 'render_admin_settings')
        );
    }

    public function render_admin_settings() {
        if (
            isset($_POST['egp_time_nonce']) &&
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['egp_time_nonce'])), 'egp_time_settings')
        ) {
            $days = array();
            if (!empty($_POST['business_days']) && is_array($_POST['business_days'])) {
                foreach ((array) wp_unslash($_POST['business_days']) as $day) {
                    $day = sanitize_text_field($day);
                    if (in_array($day, array('sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'), true)) {
                        $days[] = $day;
                    }
                }
            }

            $settings = array(
                'default_timezone_mode' => sanitize_text_field(wp_unslash($_POST['default_timezone_mode'] ?? 'site')),
                'business_start' => sanitize_text_field(wp_unslash($_POST['business_start'] ?? '09:00')),
                'business_end' => sanitize_text_field(wp_unslash($_POST['business_end'] ?? '17:00')),
                'business_days' => $days,
            );
            $this->save_settings($settings);
            echo '<div class="notice notice-success"><p>' . esc_html__('Time settings saved.', 'elementor-geo-popup') . '</p></div>';
        }

        $timezone_mode = $this->get_setting('default_timezone_mode', 'site');
        $business_start = $this->get_setting('business_start', '09:00');
        $business_end = $this->get_setting('business_end', '17:00');
        $business_days = (array) $this->get_setting('business_days', array('mon', 'tue', 'wed', 'thu', 'fri'));
        $all_days = array(
            'sun' => __('Sunday', 'elementor-geo-popup'),
            'mon' => __('Monday', 'elementor-geo-popup'),
            'tue' => __('Tuesday', 'elementor-geo-popup'),
            'wed' => __('Wednesday', 'elementor-geo-popup'),
            'thu' => __('Thursday', 'elementor-geo-popup'),
            'fri' => __('Friday', 'elementor-geo-popup'),
            'sat' => __('Saturday', 'elementor-geo-popup'),
        );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Time Targeting Settings', 'elementor-geo-popup'); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field('egp_time_settings', 'egp_time_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Default Timezone Mode', 'elementor-geo-popup'); ?></th>
                        <td>
                            <select name="default_timezone_mode">
                                <option value="site" <?php selected($timezone_mode, 'site'); ?>><?php echo esc_html__('WordPress Site Timezone', 'elementor-geo-popup'); ?></option>
                                <option value="utc" <?php selected($timezone_mode, 'utc'); ?>><?php echo esc_html__('UTC', 'elementor-geo-popup'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Default Business Start', 'elementor-geo-popup'); ?></th>
                        <td><input type="time" name="business_start" value="<?php echo esc_attr($business_start); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Default Business End', 'elementor-geo-popup'); ?></th>
                        <td><input type="time" name="business_end" value="<?php echo esc_attr($business_end); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Default Business Days', 'elementor-geo-popup'); ?></th>
                        <td>
                            <?php foreach ($all_days as $key => $label): ?>
                                <label style="display:inline-block;margin-right:12px;">
                                    <input type="checkbox" name="business_days[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $business_days, true)); ?> />
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save Settings', 'elementor-geo-popup')); ?>
            </form>
        </div>
        <?php
    }

    public function register_time_controls($element) {
        if (!class_exists('\Elementor\Controls_Manager')) {
            return;
        }

        $element->start_controls_section(
            'egp_time_targeting',
            array(
                'label' => __('Time Targeting', 'elementor-geo-popup'),
                'tab' => \Elementor\Controls_Manager::TAB_ADVANCED,
            )
        );

        $element->add_control(
            'egp_time_enabled',
            array(
                'label' => __('Enable Time Targeting', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('On', 'elementor-geo-popup'),
                'label_off' => __('Off', 'elementor-geo-popup'),
                'return_value' => 'yes',
                'default' => '',
            )
        );

        $element->add_control(
            'egp_time_mode',
            array(
                'label' => __('Time Mode', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'business',
                'options' => array(
                    'business' => __('Business Hours', 'elementor-geo-popup'),
                    'custom' => __('Custom Time Window', 'elementor-geo-popup'),
                ),
                'condition' => array('egp_time_enabled' => 'yes'),
            )
        );

        $element->add_control(
            'egp_time_timezone',
            array(
                'label' => __('Timezone', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'site',
                'options' => array(
                    'site' => __('WordPress Site Timezone', 'elementor-geo-popup'),
                    'utc' => __('UTC', 'elementor-geo-popup'),
                ),
                'condition' => array('egp_time_enabled' => 'yes'),
            )
        );

        $element->add_control(
            'egp_time_days',
            array(
                'label' => __('Active Days', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'default' => array('mon', 'tue', 'wed', 'thu', 'fri'),
                'options' => array(
                    'sun' => __('Sunday', 'elementor-geo-popup'),
                    'mon' => __('Monday', 'elementor-geo-popup'),
                    'tue' => __('Tuesday', 'elementor-geo-popup'),
                    'wed' => __('Wednesday', 'elementor-geo-popup'),
                    'thu' => __('Thursday', 'elementor-geo-popup'),
                    'fri' => __('Friday', 'elementor-geo-popup'),
                    'sat' => __('Saturday', 'elementor-geo-popup'),
                ),
                'condition' => array('egp_time_enabled' => 'yes'),
            )
        );

        $element->add_control(
            'egp_time_start',
            array(
                'label' => __('Start Time (HH:MM)', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '09:00',
                'placeholder' => '09:00',
                'condition' => array('egp_time_enabled' => 'yes'),
            )
        );

        $element->add_control(
            'egp_time_end',
            array(
                'label' => __('End Time (HH:MM)', 'elementor-geo-popup'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '17:00',
                'placeholder' => '17:00',
                'condition' => array('egp_time_enabled' => 'yes'),
            )
        );

        $element->end_controls_section();
    }

    public function apply_time_targeting($element) {
        if (!is_object($element) || !method_exists($element, 'get_settings_for_display')) {
            return;
        }

        $settings = $element->get_settings_for_display();
        if (empty($settings['egp_time_enabled']) || $settings['egp_time_enabled'] !== 'yes') {
            return;
        }

        if (!$this->is_targeting_condition_met($settings)) {
            if (method_exists($element, 'add_render_attribute')) {
                $element->add_render_attribute('_wrapper', 'style', 'display:none !important;');
            }
        }
    }

    public function render_debug_badge() {
        if (is_admin() || wp_doing_ajax() || !current_user_can('manage_options')) {
            return;
        }
        if (!isset($_GET['egp_time_debug']) || sanitize_text_field(wp_unslash($_GET['egp_time_debug'])) !== '1') {
            return;
        }

        $tz = $this->resolve_timezone($this->get_setting('default_timezone_mode', 'site'));
        $now_ts = current_time('timestamp', true);
        $current = (new DateTimeImmutable('@' . $now_ts))->setTimezone($tz);
        $day = strtolower(substr($current->format('D'), 0, 3));
        $time = $current->format('H:i:s');
        $label = sprintf(
            'EGP Time Debug | tz=%s | day=%s | time=%s',
            esc_html($tz->getName()),
            esc_html($day),
            esc_html($time)
        );

        echo '<div style="position:fixed;right:12px;bottom:12px;z-index:99999;background:#111;color:#fff;padding:8px 10px;border-radius:6px;font:12px/1.4 monospace;opacity:.9;">' . $label . '</div>';
    }

    protected function is_targeting_condition_met($settings, $context = array()) {
        $timezone_mode = $settings['egp_time_timezone'] ?? $this->get_setting('default_timezone_mode', 'site');
        $tz = $this->resolve_timezone($timezone_mode);
        $now_ts = current_time('timestamp', true);
        $current = new DateTimeImmutable('@' . $now_ts);
        $current = $current->setTimezone($tz);

        $day = strtolower($current->format('D'));
        $day = substr($day, 0, 3);

        $days = $settings['egp_time_days'] ?? $this->get_setting('business_days', array('mon', 'tue', 'wed', 'thu', 'fri'));
        if (!is_array($days)) {
            $days = array();
        }
        if (!empty($days) && !in_array($day, $days, true)) {
            return false;
        }

        $start = $this->normalize_time($settings['egp_time_start'] ?? $this->get_setting('business_start', '09:00'));
        $end = $this->normalize_time($settings['egp_time_end'] ?? $this->get_setting('business_end', '17:00'));
        if (!$start || !$end) {
            return true;
        }

        $now_minutes = ((int) $current->format('H')) * 60 + (int) $current->format('i');
        $start_minutes = $this->time_to_minutes($start);
        $end_minutes = $this->time_to_minutes($end);

        if ($start_minutes <= $end_minutes) {
            return $now_minutes >= $start_minutes && $now_minutes <= $end_minutes;
        }

        // Overnight window, e.g. 22:00 -> 04:00
        return $now_minutes >= $start_minutes || $now_minutes <= $end_minutes;
    }

    private function resolve_timezone($mode) {
        if ($mode === 'utc') {
            return new DateTimeZone('UTC');
        }

        $site_tz = wp_timezone_string();
        if (!empty($site_tz)) {
            return new DateTimeZone($site_tz);
        }

        return new DateTimeZone('UTC');
    }

    private function normalize_time($value) {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $m)) {
            return null;
        }
        return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }

    private function time_to_minutes($value) {
        list($h, $m) = array_map('intval', explode(':', $value));
        return ($h * 60) + $m;
    }
}

