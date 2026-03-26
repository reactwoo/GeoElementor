<?php
/**
 * AI translation add-on pipeline scaffold.
 *
 * @package ElementorGeoPopup
 */

if (!defined('ABSPATH')) {
    exit;
}

class EGP_AI_Translation {

    /**
     * Setup hooks.
     *
     * @return void
     */
    public static function init() {
        add_action('egp_ai_translation_generate', array(__CLASS__, 'handle_generate_request'), 10, 3);
    }

    /**
     * Generate or update a translated page draft/publication.
     *
     * @param int    $source_page_id Source page ID.
     * @param string $locale         Locale or country code.
     * @param array  $args           Extra args.
     * @return int|WP_Error
     */
    public static function generate_translation($source_page_id, $locale, $args = array()) {
        $source_page_id = intval($source_page_id);
        $source = get_post($source_page_id);
        if (!$source || $source->post_type !== 'page') {
            return new WP_Error('invalid_source', __('Invalid source page', 'elementor-geo-popup'));
        }

        $approval_mode = get_option('egp_ai_translation_approval_mode', 'manual_review');
        $approval_mode = $approval_mode === 'auto_approve' ? 'auto_approve' : 'manual_review';
        $locale = strtoupper(substr(sanitize_text_field((string) $locale), 0, 10));

        $title = wp_strip_all_tags($source->post_title) . ' (' . $locale . ')';
        $content = self::build_translated_content($source->post_content, $locale, $args);
        if (is_wp_error($content)) {
            return $content;
        }

        $postarr = array(
            'post_type' => 'page',
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $approval_mode === 'auto_approve' ? 'publish' : 'draft',
        );

        $postarr = apply_filters('egp_ai_translation_postarr', $postarr, $source, $locale, $args, $approval_mode);
        $target_id = wp_insert_post($postarr, true);
        if (is_wp_error($target_id)) {
            return $target_id;
        }

        update_post_meta($target_id, '_egp_translation_source_page_id', $source_page_id);
        update_post_meta($target_id, '_egp_translation_locale', $locale);
        update_post_meta($target_id, '_egp_translation_approval_mode', $approval_mode);

        /**
         * Fires after translation page is generated.
         */
        do_action('egp_ai_translation_generated', $target_id, $source_page_id, $locale, $approval_mode);

        return $target_id;
    }

    /**
     * Action handler wrapper.
     *
     * @param int    $source_page_id Source page id.
     * @param string $locale Locale.
     * @param array  $args Args.
     * @return void
     */
    public static function handle_generate_request($source_page_id, $locale, $args = array()) {
        self::generate_translation($source_page_id, $locale, $args);
    }

    /**
     * Build translated content through a pluggable pipeline.
     *
     * @param string $content Source content.
     * @param string $locale Target locale.
     * @param array  $args Extra args.
     * @return string|WP_Error
     */
    private static function build_translated_content($content, $locale, $args = array()) {
        $generated = apply_filters('egp_ai_translate_content', null, $content, $locale, $args);
        if (is_wp_error($generated)) {
            return $generated;
        }
        if (is_string($generated) && $generated !== '') {
            return $generated;
        }

        // Default placeholder keeps pipeline safe when no provider is attached.
        return $content . "\n\n<!-- AI translation pending provider for locale: " . esc_html($locale) . " -->";
    }
}

