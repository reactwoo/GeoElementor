<?php
/**
 * Geolocation Detection
 * 
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Geolocation Detection Class
 */
class EGP_Geo_Detect {
    
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
    public function __construct() {
        add_action('wp_head', array($this, 'inject_geo_popup_script'), 1);
        add_action('wp_ajax_egp_get_visitor_country', array($this, 'ajax_get_visitor_country'));
        add_action('wp_ajax_nopriv_egp_get_visitor_country', array($this, 'ajax_get_visitor_country'));
        // Shortcode for quick country testing
        add_action('init', function () {
            add_shortcode('egp_country', array($this, 'shortcode_egp_country'));
        });
        // Optional on-page debug badge when debug mode enabled
        add_action('wp_footer', array($this, 'maybe_render_debug_badge'), 99);
    }
    
    /**
     * Inject geo popup script
     */
    public function inject_geo_popup_script() {
        // Only inject on frontend pages
        if (is_admin() || wp_doing_ajax()) {
            if (get_option('egp_debug_mode')) { error_log('EGP: Skipping guard injection (admin or ajax)'); }
            return;
        }
        
        // Get visitor's country
        $country = $this->get_visitor_country();
        
        if (!$country) {
            if (get_option('egp_debug_mode')) { error_log('EGP: Skipping guard injection (no country)'); }
            return;
        }
        
        // Optional head guard (disabled by default to avoid interfering with popup close buttons)
        $enable_head_guard = (bool) get_option('egp_head_guard_enabled', false);
        if ($enable_head_guard) {
            // Prevent early popup flashes before the guard is active
            echo '<style id="egp-hide-popups">.elementor-popup-modal,.dialog-widget{display:none!important;visibility:hidden!important;}</style>';
            if (get_option('egp_debug_mode')) { error_log('EGP: Head guard style injected'); }

            // Inject guard to prevent non-matching popups from showing
            if (get_option('egp_debug_mode')) { error_log('EGP: Injecting head guard for country ' . $country); }
            $this->render_popup_guard_script($country);
        } else {
            // Inject only the JS guard (no CSS), so popups can still be closed and are filtered by country
            if (get_option('egp_debug_mode')) { error_log('EGP: Injecting JS guard only for country ' . $country); }
            $this->render_popup_guard_script($country);
        }

        // Optionally trigger a specifically matched popup (if you want auto-open behavior)
        // $popup_id = $this->get_matching_popup($country);
        // if ($popup_id) {
        //     $this->render_popup_script($popup_id, $country);
        // }
    }
    
    /**
     * Check if there are any geo-targeted popups
     */
    private function has_geo_targeted_popups() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'egp_popup_countries';
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE enabled = 1");
        
        return $count > 0;
    }
    
    /**
     * Get visitor's country
     */
    public function get_visitor_country() {
        // Check for cached result first
        $cached_country = wp_cache_get('egp_visitor_country_' . $this->get_visitor_ip(), 'egp_geo');
        
        if ($cached_country !== false) {
            return $cached_country;
        }
        
        // Get visitor's IP
        $ip = $this->get_visitor_ip();
        
        if (!$ip || $this->is_private_ip($ip)) {
            return false;
        }
        
        // Look up country using MaxMind database
        $country = $this->lookup_country($ip);
        
        if ($country) {
            // Cache the result for 1 hour
            wp_cache_set('egp_visitor_country_' . $ip, $country, 'egp_geo', HOUR_IN_SECONDS);
        }
        
        return $country;
    }
    
    /**
     * Get visitor's IP address
     */
    private function get_visitor_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    /**
     * Check if IP is private
     */
    private function is_private_ip($ip) {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    
    /**
     * Look up country using MaxMind database
     */
    private function lookup_country($ip) {
        $database_path = get_option('egp_database_path');
        
        if (!$database_path || !file_exists($database_path)) {
            if (get_option('egp_debug_mode')) {
                error_log('EGP: MaxMind database not found at ' . $database_path);
            }
            return false;
        }
        
        try {
            // Check if geoip2 library is available
            if (!class_exists('GeoIp2\Database\Reader')) {
                if (get_option('egp_debug_mode')) {
                    error_log('EGP: GeoIP2 library not available');
                }
                return false;
            }
            
            $reader = new GeoIp2\Database\Reader($database_path);
            $record = $reader->country($ip);
            
            $country_code = $record->country->isoCode;
            
            if (get_option('egp_debug_mode')) {
                error_log('EGP: IP ' . $ip . ' resolved to country ' . $country_code);
            }
            
            $reader->close();
            
            return $country_code;
            
        } catch (Exception $e) {
            if (get_option('egp_debug_mode')) {
                error_log('EGP: Error looking up country for IP ' . $ip . ': ' . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Get matching popup for country
     */
    private function get_matching_popup($country) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'egp_popup_countries';
        
        // Find popups that target this country
        $popups = $wpdb->get_results($wpdb->prepare(
            "SELECT popup_id, countries, fallback_behavior FROM $table_name 
             WHERE enabled = 1 AND JSON_CONTAINS(countries, %s)",
            json_encode($country)
        ));
        
        if (empty($popups)) {
            // No explicit match; do not force any popup
            return 0;
        }
        
        // Return the first matching popup
        return $popups[0]->popup_id;
    }
    
    /**
     * Get fallback popup based on global settings
     */
    private function get_fallback_popup() {
        $fallback_behavior = get_option('egp_fallback_behavior', 'show_to_all');
        
        switch ($fallback_behavior) {
            case 'show_default':
                return get_option('egp_default_popup_id', 0);
            case 'show_to_all':
                // Return a random popup or the first available
                return $this->get_random_popup();
            case 'show_to_none':
            default:
                return 0;
        }
    }
    
    /**
     * Get random popup for fallback
     */
    private function get_random_popup() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'egp_popup_countries';
        
        $popup_id = $wpdb->get_var("SELECT popup_id FROM $table_name WHERE enabled = 1 ORDER BY RAND() LIMIT 1");
        
        return $popup_id ?: 0;
    }
    
    /**
     * Render popup trigger script
     */
    private function render_popup_script($popup_id, $country) {
        if (!$popup_id) {
            return;
        }
        
        // Check if popup exists and is published
        $popup = get_post($popup_id);
        if (!$popup || $popup->post_status !== 'publish') {
            return;
        }
        
        ?>
        <script type="text/javascript">
        (function() {
            'use strict';
            
            // Wait for Elementor to be ready
            function triggerGeoPopup() {
                if (typeof elementorProFrontend !== 'undefined' && 
                    elementorProFrontend.modules && 
                    elementorProFrontend.modules.popup) {
                    
                    // Add a small delay to ensure page is fully loaded
                    setTimeout(function() {
                        elementorProFrontend.modules.popup.showPopup({
                            id: <?php echo intval($popup_id); ?>,
                            isEvent: false
                        });
                        
                        <?php if (get_option('egp_debug_mode')) : ?>
                        console.log('EGP: Triggering popup <?php echo intval($popup_id); ?> for country <?php echo esc_js($country); ?>');
                        <?php endif; ?>
                    }, 1000);
                    
                } else {
                    // Retry after a short delay
                    setTimeout(triggerGeoPopup, 500);
                }
            }
            
            // Start the process when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', triggerGeoPopup);
            } else {
                triggerGeoPopup();
            }
        })();
        </script>
        <?php
    }
    
    /**
     * AJAX get visitor country
     */
    public function ajax_get_visitor_country() {
        check_ajax_referer('egp_geo_nonce', 'nonce');
        
        $country = $this->get_visitor_country();
        
        if ($country) {
            wp_send_json_success(array('country' => $country));
        } else {
            wp_send_json_error(__('Could not determine country', 'elementor-geo-popup'));
        }
    }
    
    /**
     * Shortcode: [egp_country]
     */
    public function shortcode_egp_country() {
        $ip = $this->get_visitor_ip();
        $country = $this->get_visitor_country();
        $country_name = $country ? self::get_country_name($country) : __('Unknown', 'elementor-geo-popup');
        ob_start();
        echo '<div class="egp-country-test" style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:4px;display:inline-block;background:#f8fafc;">';
        echo '<strong>' . esc_html__('Detected Country:', 'elementor-geo-popup') . '</strong> ' . esc_html($country ?: '—') . ' – ' . esc_html($country_name);
        echo ' &nbsp; <strong>IP:</strong> ' . esc_html($ip ?: '—');
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Inject a JS guard that prevents popups from showing if geo rules do not match
     */
    private function render_popup_guard_script($country) {
        $country = strtoupper($country);
        // Build rules from popup page settings
        $rules = array();
        $query = new WP_Query(array(
            'post_type' => 'elementor_library',
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'fields' => 'ids',
        ));
        if ($query->have_posts()) {
            foreach ($query->posts as $popup_id) {
                // Ensure this library doc is a popup
                $type = get_post_meta($popup_id, '_elementor_template_type', true);
                if ($type !== 'popup') {
                    continue;
                }
                $settings = get_post_meta($popup_id, '_elementor_page_settings', true);
                if (!is_array($settings)) {
                    continue;
                }
                if (isset($settings['egp_enable_geo_targeting']) && $settings['egp_enable_geo_targeting'] === 'yes') {
                    if (get_option('egp_debug_mode')) {
                        error_log('EGP: Popup ' . $popup_id . ' Elementor page settings: ' . print_r($settings, true));
                    }
                    $countries = array();
                    if (!empty($settings['egp_countries']) && is_array($settings['egp_countries'])) {
                        foreach ($settings['egp_countries'] as $c) {
                            $countries[] = strtoupper(sanitize_text_field($c));
                        }
                    }
                    $rules[(int) $popup_id] = array(
                        'enabled' => true,
                        'countries' => $countries,
                        'fallback' => isset($settings['egp_fallback_behavior']) ? sanitize_text_field($settings['egp_fallback_behavior']) : 'inherit',
                    );
                }
            }
        }
        wp_reset_postdata();

        if (get_option('egp_debug_mode')) {
            error_log('EGP: Built ' . count($rules) . ' geo rules for head guard');
        }
        $json_rules = wp_json_encode($rules);
        $applyPreferred = (bool) get_option('egp_apply_preferred_to_untargeted', false);
        $preferred = get_option('egp_preferred_countries', array('US','CA','GB'));
        if (!is_array($preferred)) { $preferred = array(); }
        $preferred = array_map('strtoupper', $preferred);
        // Server-side CSS guard: hide popups that do not include this country (non-invasive, does not affect allowed popups)
        $disallowed = array();
        foreach ($rules as $pid => $rule) {
            if (!isset($rule['enabled']) || !$rule['enabled']) {
                continue;
            }
            $countries = isset($rule['countries']) && is_array($rule['countries']) ? array_map('strtoupper', $rule['countries']) : array();
            if (!in_array($country, $countries, true)) {
                $disallowed[] = (int) $pid;
            }
        }
        if (get_option('egp_debug_mode')) {
            error_log('EGP: Rule IDs: ' . implode(',', array_keys($rules)) . ' | Disallowed for ' . $country . ': ' . (empty($disallowed) ? '(none)' : implode(',', $disallowed)));
        }
        if (!empty($disallowed)) {
            echo '<style id="egp-popup-css-guard">';
            foreach ($disallowed as $pid) {
                $pid = (int) $pid;
                echo '.elementor-popup-modal[data-elementor-id="' . $pid . '"],.dialog-widget[data-elementor-id="' . $pid . '"],#elementor-popup-modal-' . $pid . '{display:none!important;visibility:hidden!important;}';
            }
            echo '</style>';
        }
        // Add minimal close fallback to assist Elementor-created popups without altering allowed/disallowed logic
        ?>
        <script type="text/javascript">
        (function(){
            var debug = <?php echo get_option('egp_debug_mode') ? 'true' : 'false'; ?>;
            // Mirror rules map in JS to know allowed vs disallowed for optional enhancements
            var egpRules = <?php echo $json_rules ? $json_rules : '{}'; ?>;
            function isAllowed(id){
                try { id = parseInt(id,10); } catch(e){}
                var r = egpRules && egpRules[id];
                if (!r || !r.enabled) { return true; }
                var t = String(<?php echo json_encode(strtoupper($country)); ?>);
                return Array.isArray(r.countries) && r.countries.indexOf(t) !== -1;
            }
            function getPopupIdFromNode(node){
                try {
                    var el = node instanceof Element ? node : null;
                    while (el && el !== document.documentElement){
                        if (el.classList && el.classList.contains('elementor-popup-modal')){
                            var idAttr = el.getAttribute('data-elementor-id') || el.id || '';
                            var pid = parseInt((idAttr||'').replace(/\D+/g,'')||'0', 10);
                            return pid || null;
                        }
                        el = el.parentNode;
                    }
                } catch(e) {}
                return null;
            }
            function getActivePopupId(){
                try {
                    var mod = window.elementorProFrontend && elementorProFrontend.modules && elementorProFrontend.modules.popup;
                    if (!mod) { return null; }
                    if (typeof mod.getActiveModal === 'function') {
                        var m = mod.getActiveModal();
                        if (m && typeof m.getSettings === 'function') { return m.getSettings('id') || null; }
                    }
                    if (mod.activeModal && typeof mod.activeModal.getSettings === 'function') {
                        return mod.activeModal.getSettings('id') || null;
                    }
                } catch(e) {}
                return null;
            }
            function ensureClose(pid){
                try {
                    if (!pid) { return; }
                    var mod = window.elementorProFrontend && elementorProFrontend.modules && elementorProFrontend.modules.popup;
                    if (!mod || typeof mod.closePopup !== 'function') { return; }
                    setTimeout(function(){
                        try { mod.closePopup({ id: pid }); if (debug && window.console) console.log('[EGP] fallback close invoked for', pid); } catch(e){}
                        try { sessionStorage.setItem('egp_closed_'+pid, '1'); } catch(e){}
                    }, 0);
                } catch(e){}
            }
            function hardClose(pid){
                try {
                    if (!pid) { return; }
                    var sel = '.elementor-popup-modal[data-elementor-id="' + pid + '"]';
                    var modal = document.querySelector(sel);
                    if (!modal) { return; }
                    // 1) Try clicking native close button
                    var btn = modal.querySelector('.dialog-close-button, .elementor-button--close');
                    if (btn) { try { btn.click(); } catch(e){} }
                    // 2) Hide via style as last resort
                    modal.style.display = 'none';
                    modal.style.visibility = 'hidden';
                    // 3) Clean body state
                    try { document.body.classList.remove('elementor-popup-modal-open'); } catch(e){}
                    try { sessionStorage.setItem('egp_closed_'+pid, '1'); } catch(e){}
                    // 4) Remove overlay/backdrop elements if present
                    try {
                        var overlays = document.querySelectorAll('.dialog-widget-overlay, .dialog-overlay, .elementor-popup-modal-overlay');
                        overlays.forEach(function(ov){ ov.style.display='none'; ov.style.visibility='hidden'; if (ov.parentNode) { ov.parentNode.removeChild(ov); } });
                    } catch(e){}
                    // 5) Remove the modal node entirely to avoid reflows trapping focus
                    try { if (modal.parentNode) { modal.parentNode.removeChild(modal); } } catch(e){}
                    // 6) Restore scrolling in case Elementor locked it
                    try { document.documentElement.style.overflow=''; document.body.style.overflow=''; } catch(e){}
                    if (debug && window.console) console.log('[EGP] hardClose applied for', pid);
                } catch(e){}
            }
            document.addEventListener('click', function(evt){
                try {
                    var t = evt.target;
                    if (!t) { return; }
                    var isCloseBtn = t.closest ? t.closest('.dialog-close-button, .elementor-button--close') : null;
                    var isOverlay = t.classList && (t.classList.contains('dialog-overlay') || t.classList.contains('elementor-popup-modal-overlay'));
                    if (isCloseBtn || isOverlay){
                        var pid = getPopupIdFromNode(t) || getActivePopupId();
                        if (debug && window.console) console.log('[EGP] close intent detected; pid=', pid);
                        ensureClose(pid);
                        setTimeout(function(){ var active = getActivePopupId(); if (active === pid) { hardClose(pid); } }, 150);
                    }
                } catch(e){}
            }, true);
            document.addEventListener('keydown', function(evt){
                try {
                    if (evt.key === 'Escape' || evt.key === 'Esc' || evt.keyCode === 27){
                        var pid = getActivePopupId();
                        if (!pid) {
                            var open = document.querySelector('.elementor-popup-modal');
                            if (open) { pid = getPopupIdFromNode(open); }
                        }
                        if (pid) { ensureClose(pid); if (debug && window.console) console.log('[EGP] ESC close intent; pid=', pid); setTimeout(function(){ var active = getActivePopupId(); if (active === pid) { hardClose(pid); } }, 150); }
                    }
                } catch(e){}
            }, true);

            // Prevent immediate re-open of a popup that was closed this session
            (function(){
                function getPidFromArgs(args){
                    if (!args) return null; if (typeof args === 'number') return args; if (args.id) return args.id; if (args.popup && args.popup.id) return args.popup.id; return null;
                }
                function patchReopenBlock(){
                    try {
                        var mod = window.elementorProFrontend && window.elementorProFrontend.modules && window.elementorProFrontend.modules.popup;
                        if (mod && typeof mod.showPopup === 'function' && !mod.__egpClosedPatched){
                            var original = mod.showPopup;
                            mod.showPopup = function(){
                                var pid = getPidFromArgs(arguments[0]);
                                try { var closed = pid ? sessionStorage.getItem('egp_closed_'+pid) === '1' : false; } catch(e) { var closed = false; }
                                if (closed){ if (debug && window.console) console.log('[EGP] blocked reopen for closed popup', pid); return; }
                                return original.apply(this, arguments);
                            };
                            mod.__egpClosedPatched = true;
                            if (debug && window.console) console.log('[EGP] reopen-block patch active');
                            return true;
                        }
                    } catch(e){}
                    return false;
                }
                function ready(){ if (!patchReopenBlock()){ setTimeout(ready, 100); } }
                ready();
            })();

            // Ensure a close button exists for allowed popups (Elementor templates may omit it)
            try {
                var obs = new MutationObserver(function(muts){
                    muts.forEach(function(m){
                        if (!m.addedNodes || !m.addedNodes.length) { return; }
                        m.addedNodes.forEach(function(node){
                            try {
                                if (!(node instanceof Element)) { return; }
                                if (node.classList && node.classList.contains('elementor-popup-modal')){
                                    var idAttr = node.getAttribute('data-elementor-id') || node.id || '';
                                    var pid = parseInt((idAttr||'').replace(/\D+/g,'')||'0', 10);
                                    if (!pid || !isAllowed(pid)) { return; }
                                    // If no standard close button present, inject one
                                    var hasClose = node.querySelector('.dialog-close-button, .elementor-button--close');
                                    if (!hasClose){
                                        if (debug && window.console) console.log('[EGP] injecting close button for popup', pid);
                                        var btn = document.createElement('div');
                                        btn.className = 'dialog-close-button';
                                        btn.setAttribute('role','button');
                                        btn.setAttribute('aria-label','Close');
                                        btn.innerHTML = '<span aria-hidden="true">×</span>';
                                        btn.style.position = 'absolute';
                                        btn.style.top = '8px';
                                        btn.style.right = '8px';
                                        btn.style.cursor = 'pointer';
                                        btn.addEventListener('click', function(){ ensureClose(pid); });
                                        // Append into the widget container
                                        var container = node.querySelector('.dialog-widget') || node;
                                        container.appendChild(btn);
                                    }
                                }
                            } catch(e){}
                        });
                    });
                });
                obs.observe(document.documentElement || document.body, { childList: true, subtree: true });
            } catch(e){}
        })();
        </script>
        <?php
        // Only CSS guard plus close fallback is needed; skip other JS guards to avoid lifecycle conflicts
        return;
        ?>
        <script type="text/javascript">
        (function(){
            var egpCountry = <?php echo json_encode($country); ?>;
            var egpRules = <?php echo $json_rules ? $json_rules : '{}'; ?>;
            var egpApplyPreferredToUntargeted = <?php echo $applyPreferred ? 'true' : 'false'; ?>;
            var egpPreferredCountries = <?php echo wp_json_encode($preferred); ?>;
            try { window.egpCountry = egpCountry; window.egpRules = egpRules; } catch(e){}
            var cssHider = document.getElementById('egp-hide-popups');
            if (<?php echo get_option('egp_debug_mode') ? 'true' : 'false'; ?>) { try { console.log('[EGP] init guard; country:', egpCountry, 'rules:', Object.keys(egpRules||{}).length, 'applyPreferredToUntargeted:', egpApplyPreferredToUntargeted, 'preferred:', egpPreferredCountries); } catch(e){} }
            function shouldAllow(id){
                try{ id = parseInt(id, 10); }catch(e){}
                if (<?php echo get_option('egp_debug_mode') ? 'true' : 'false'; ?>) { try { console.log('[EGP] shouldAllow? id=', id); } catch(e){} }
                var r = egpRules[id];
                if(!r || !r.enabled){
                    if (!egpApplyPreferredToUntargeted) { return true; }
                    var t = String(egpCountry || '').toUpperCase();
                    var allowUntargeted = Array.isArray(egpPreferredCountries) && egpPreferredCountries.indexOf(t) !== -1;
                    if (<?php echo get_option('egp_debug_mode') ? 'true' : 'false'; ?>) { try { console.log('[EGP] untargeted popup decision for', id, 'country', t, 'allow?', allowUntargeted); } catch(e){} }
                    return allowUntargeted;
                }
                if(!egpCountry){ return r.fallback === 'show_to_all'; }
                var target = String(egpCountry || '').toUpperCase();
                var allowed = Array.isArray(r.countries) && r.countries.indexOf(target) !== -1;
                if (<?php echo get_option('egp_debug_mode') ? 'true' : 'false'; ?>) { try { console.log('[EGP] decision for', id, 'target=', target, 'in rules?', allowed, 'rule:', r); } catch(e){} }
                return allowed;
            }
            function getPopupIdFromInstance(inst){
                try {
                    if (inst && inst.getSettings){ return inst.getSettings('id'); }
                } catch(e) {}
                return null;
            }
            // Do not attach jQuery event bus interceptions; prefer a minimal showPopup patch

            function getPidFromArgs(args){
                try {
                    if (!args) { return null; }
                    if (typeof args === 'number') { return args; }
                    if (args.id) { return args.id; }
                    if (args.popup && args.popup.id) { return args.popup.id; }
                } catch(e) {}
                return null;
            }

            function patchShow(){
                try {
                    var mod = window.elementorProFrontend && window.elementorProFrontend.modules && window.elementorProFrontend.modules.popup;
                    if (mod && typeof mod.showPopup === 'function' && !mod.__egpPatched) {
                        if (<?php echo get_option('egp_debug_mode') ? 'true' : 'false'; ?>) { try { console.log('[EGP] patching showPopup (safe)'); } catch(e){} }
                        var originalShow = mod.showPopup;
                        mod.showPopup = function(){
                            var pid = getPidFromArgs(arguments[0]);
                            if (<?php echo get_option('egp_debug_mode') ? 'true' : 'false'; ?>) { try { console.log('[EGP] showPopup?', pid, arguments[0]); } catch(e){} }
                            if (pid && !shouldAllow(pid)){
                                if (<?php echo get_option('egp_debug_mode') ? 'true' : 'false'; ?> && window.console && console.log){ console.log('[EGP] blocked showPopup', pid, 'for country', egpCountry); }
                                return; // block disallowed
                            }
                            return originalShow.apply(this, arguments);
                        };
                        mod.__egpPatched = true;
                        return true;
                    }
                } catch(e) { if (window.console && console.warn){ console.warn('[EGP] guard patch error', e); } }
                return false;
            }

            function patchDocumentsManager(){
                try {
                    var mgr = window.elementorFrontend && elementorFrontend.documents && elementorFrontend.documents.manager && elementorFrontend.documents.manager.documents && elementorFrontend.documents.manager.documents[0];
                    if (mgr && !mgr.__egpPatched) {
                        if (<?php echo get_option('egp_debug_mode') ? 'true' : 'false'; ?>) { try { console.log('[EGP] patching documents[0] show/trigger'); } catch(e){} }
                        var origShow = typeof mgr.showPopup === 'function' ? mgr.showPopup : null;
                        var origTrigger = typeof mgr.triggerPopup === 'function' ? mgr.triggerPopup : null;
                        if (origShow) {
                            mgr.showPopup = function(pid){
                                var id = pid;
                                try { if (pid && typeof pid === 'object' && pid.id){ id = pid.id; } } catch(e){}
                                if (id && !shouldAllow(id)){
                                    if (<?php echo get_option('egp_debug_mode') ? 'true' : 'false'; ?> && window.console && console.log){ console.log('[EGP] blocked documents.showPopup', id, 'for country', egpCountry); }
                                    return false;
                                }
                                return origShow.apply(this, arguments);
                            };
                        }
                        if (origTrigger) {
                            mgr.triggerPopup = function(pid){
                                var id = pid;
                                try { if (pid && typeof pid === 'object' && pid.id){ id = pid.id; } } catch(e){}
                                if (id && !shouldAllow(id)){
                                    if (<?php echo get_option('egp_debug_mode') ? 'true' : 'false'; ?> && window.console && console.log){ console.log('[EGP] blocked documents.triggerPopup', id, 'for country', egpCountry); }
                                    return false;
                                }
                                return origTrigger.apply(this, arguments);
                            };
                        }
                        mgr.__egpPatched = true;
                        return true;
                    }
                } catch(e) { if (window.console && console.warn){ console.warn('[EGP] documents patch error', e); } }
                return false;
            }
            function unhidePopups(){
                if (<?php echo get_option('egp_debug_mode') ? 'true' : 'false'; ?>) { try { console.log('[EGP] unhidePopups'); } catch(e){} }
                if (cssHider && cssHider.parentNode){ cssHider.parentNode.removeChild(cssHider); }
                var modals = document.querySelectorAll('.elementor-popup-modal,.dialog-widget');
                modals.forEach(function(m){ m.style.removeProperty('display'); m.style.removeProperty('visibility'); });
            }

            function closeDisallowedIfOpen(){
                try{
                    var open = document.querySelectorAll('.elementor-popup-modal');
                    open.forEach(function(el){
                        var idAttr = el.getAttribute('data-elementor-id') || el.id || '';
                        var pid = parseInt(idAttr.replace(/\D+/g,'') || '0', 10);
                        if (<?php echo get_option('egp_debug_mode') ? 'true' : 'false'; ?>) { try { console.log('[EGP] scan open popup element', pid); } catch(e){} }
                        if (pid && !shouldAllow(pid)){
                            if (<?php echo get_option('egp_debug_mode') ? 'true' : 'false'; ?> && window.console && console.log){ console.log('[EGP] hiding disallowed popup element', pid); }
                            // Hide non-matching popup elements without touching allowed ones
                            el.style.display = 'none';
                            el.style.visibility = 'hidden';
                        }
                    });
                }catch(e){}
            }

            function observePopups(){
                try {
                    var observer = new MutationObserver(function(mutations){
                        mutations.forEach(function(m){
                            if (!m.addedNodes || !m.addedNodes.length) { return; }
                            m.addedNodes.forEach(function(node){
                                try {
                                    if (!(node instanceof Element)) { return; }
                                    if (node.matches && node.matches('.elementor-popup-modal, .dialog-widget')){
                                        var idAttr = node.getAttribute('data-elementor-id') || node.id || '';
                                        var pid = parseInt((idAttr || '').replace(/\D+/g,'') || '0', 10);
                                        if (pid && !shouldAllow(pid)){
                                            if (<?php echo get_option('egp_debug_mode') ? 'true' : 'false'; ?> && window.console && console.log){ console.log('[EGP] observer: hiding disallowed popup', pid); }
                                            node.style.display = 'none';
                                            node.style.visibility = 'hidden';
                                        }
                                    }
                                } catch(e){}
                            });
                        });
                    });
                    observer.observe(document.documentElement || document.body, { childList: true, subtree: true });
                } catch(e) { if (window.console && console.warn){ console.warn('[EGP] observer error', e); } }
            }

            function setupGuards(){
                if (<?php echo get_option('egp_debug_mode') ? 'true' : 'false'; ?>) { try { console.log('[EGP] setupGuards start'); } catch(e){} }
                // Ensure both Elementor Pro popup module and Documents manager are patched (retry until ready)
                var ok1 = patchShow();
                var ok2 = patchDocumentsManager();
                if (!ok1 || !ok2) {
                    setTimeout(setupGuards, 100);
                    return;
                }
                // Clean up any early rendered items and unhide UI
                closeDisallowedIfOpen();
                observePopups();
                unhidePopups();
                if (<?php echo get_option('egp_debug_mode') ? 'true' : 'false'; ?>) { try { console.log('[EGP] guards active (pro+docs patched)'); } catch(e){} }
            }

            // Start as soon as possible
            if (document.readyState === 'loading'){
                document.addEventListener('DOMContentLoaded', setupGuards);
            } else {
                setupGuards();
            }
        })();
        </script>
        <?php
    }

    /**
     * Render a small debug badge on the page when debug mode is enabled
     */
    public function maybe_render_debug_badge() {
        if (!get_option('egp_debug_mode')) {
            return;
        }
        // Avoid showing in admin/elementor editor previews if not desired
        if (is_admin()) {
            return;
        }
        $ip = $this->get_visitor_ip();
        $country = $this->get_visitor_country();
        $country_name = $country ? self::get_country_name($country) : __('Unknown', 'elementor-geo-popup');
        ?>
        <div style="position:fixed;z-index:2147483647;right:12px;bottom:12px;background:#111827;color:#f9fafb;border-radius:6px;padding:8px 10px;font:12px/1.4 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;box-shadow:0 6px 18px rgba(0,0,0,.2);opacity:.9;">
            <span style="opacity:.8;">EGP</span> · <strong><?php echo esc_html($country ?: '—'); ?></strong>
            <span style="opacity:.8;">(<?php echo esc_html($country_name); ?>)</span>
            <span style="margin-left:8px;opacity:.8;">IP:</span> <?php echo esc_html($ip ?: '—'); ?>
        </div>
        <?php
    }

    /**
     * Get country name from code
     */
    public static function get_country_name($country_code) {
        $countries = array(
            'US' => __('United States', 'elementor-geo-popup'),
            'CA' => __('Canada', 'elementor-geo-popup'),
            'GB' => __('United Kingdom', 'elementor-geo-popup'),
            'DE' => __('Germany', 'elementor-geo-popup'),
            'FR' => __('France', 'elementor-geo-popup'),
            'IT' => __('Italy', 'elementor-geo-popup'),
            'ES' => __('Spain', 'elementor-geo-popup'),
            'NL' => __('Netherlands', 'elementor-geo-popup'),
            'BE' => __('Belgium', 'elementor-geo-popup'),
            'CH' => __('Switzerland', 'elementor-geo-popup'),
            'AT' => __('Austria', 'elementor-geo-popup'),
            'SE' => __('Sweden', 'elementor-geo-popup'),
            'NO' => __('Norway', 'elementor-geo-popup'),
            'DK' => __('Denmark', 'elementor-geo-popup'),
            'FI' => __('Finland', 'elementor-geo-popup'),
            'PL' => __('Poland', 'elementor-geo-popup'),
            'CZ' => __('Czech Republic', 'elementor-geo-popup'),
            'HU' => __('Hungary', 'elementor-geo-popup'),
            'RO' => __('Romania', 'elementor-geo-popup'),
            'BG' => __('Bulgaria', 'elementor-geo-popup'),
            'HR' => __('Croatia', 'elementor-geo-popup'),
            'SI' => __('Slovenia', 'elementor-geo-popup'),
            'SK' => __('Slovakia', 'elementor-geo-popup'),
            'LT' => __('Lithuania', 'elementor-geo-popup'),
            'LV' => __('Latvia', 'elementor-geo-popup'),
            'EE' => __('Estonia', 'elementor-geo-popup'),
            'IE' => __('Ireland', 'elementor-geo-popup'),
            'PT' => __('Portugal', 'elementor-geo-popup'),
            'GR' => __('Greece', 'elementor-geo-popup'),
            'CY' => __('Cyprus', 'elementor-geo-popup'),
            'MT' => __('Malta', 'elementor-geo-popup'),
            'LU' => __('Luxembourg', 'elementor-geo-popup'),
            'AU' => __('Australia', 'elementor-geo-popup'),
            'NZ' => __('New Zealand', 'elementor-geo-popup'),
            'JP' => __('Japan', 'elementor-geo-popup'),
            'KR' => __('South Korea', 'elementor-geo-popup'),
            'CN' => __('China', 'elementor-geo-popup'),
            'IN' => __('India', 'elementor-geo-popup'),
            'BR' => __('Brazil', 'elementor-geo-popup'),
            'MX' => __('Mexico', 'elementor-geo-popup'),
            'AR' => __('Argentina', 'elementor-geo-popup'),
            'CL' => __('Chile', 'elementor-geo-popup'),
            'CO' => __('Colombia', 'elementor-geo-popup'),
            'PE' => __('Peru', 'elementor-geo-popup'),
            'VE' => __('Venezuela', 'elementor-geo-popup'),
            'ZA' => __('South Africa', 'elementor-geo-popup'),
            'EG' => __('Egypt', 'elementor-geo-popup'),
            'NG' => __('Nigeria', 'elementor-geo-popup'),
            'KE' => __('Kenya', 'elementor-geo-popup'),
            'MA' => __('Morocco', 'elementor-geo-popup'),
            'TN' => __('Tunisia', 'elementor-geo-popup'),
            'DZ' => __('Algeria', 'elementor-geo-popup'),
            'LY' => __('Libya', 'elementor-geo-popup'),
            'SD' => __('Sudan', 'elementor-geo-popup'),
            'ET' => __('Ethiopia', 'elementor-geo-popup'),
            'GH' => __('Ghana', 'elementor-geo-popup'),
            'CI' => __('Ivory Coast', 'elementor-geo-popup'),
            'SN' => __('Senegal', 'elementor-geo-popup'),
            'ML' => __('Mali', 'elementor-geo-popup'),
            'BF' => __('Burkina Faso', 'elementor-geo-popup'),
            'NE' => __('Niger', 'elementor-geo-popup'),
            'TD' => __('Chad', 'elementor-geo-popup'),
            'CF' => __('Central African Republic', 'elementor-geo-popup'),
            'CM' => __('Cameroon', 'elementor-geo-popup'),
            'GQ' => __('Equatorial Guinea', 'elementor-geo-popup'),
            'GA' => __('Gabon', 'elementor-geo-popup'),
            'CG' => __('Republic of the Congo', 'elementor-geo-popup'),
            'CD' => __('Democratic Republic of the Congo', 'elementor-geo-popup'),
            'AO' => __('Angola', 'elementor-geo-popup'),
            'ZM' => __('Zambia', 'elementor-geo-popup'),
            'ZW' => __('Zimbabwe', 'elementor-geo-popup'),
            'BW' => __('Botswana', 'elementor-geo-popup'),
            'NA' => __('Namibia', 'elementor-geo-popup'),
            'SZ' => __('Eswatini', 'elementor-geo-popup'),
            'LS' => __('Lesotho', 'elementor-geo-popup'),
            'MG' => __('Madagascar', 'elementor-geo-popup'),
            'MU' => __('Mauritius', 'elementor-geo-popup'),
            'SC' => __('Seychelles', 'elementor-geo-popup'),
            'KM' => __('Comoros', 'elementor-geo-popup'),
            'DJ' => __('Djibouti', 'elementor-geo-popup'),
            'SO' => __('Somalia', 'elementor-geo-popup'),
            'ER' => __('Eritrea', 'elementor-geo-popup'),
            'SS' => __('South Sudan', 'elementor-geo-popup'),
            'RW' => __('Rwanda', 'elementor-geo-popup'),
            'BI' => __('Burundi', 'elementor-geo-popup'),
            'TZ' => __('Tanzania', 'elementor-geo-popup'),
            'UG' => __('Uganda', 'elementor-geo-popup'),
            'MZ' => __('Mozambique', 'elementor-geo-popup'),
            'MW' => __('Malawi', 'elementor-geo-popup'),
        );
        
        return isset($countries[$country_code]) ? $countries[$country_code] : $country_code;
    }
}



