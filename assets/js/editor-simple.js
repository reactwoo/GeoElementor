/**
 * Simple Elementor Editor Integration
 * Handles country selection persistence and rule creation
 */

(function ($) {
    'use strict';

    // Wait for Elementor to be ready
    function initWhenReady() {
        if (typeof elementor === 'undefined' || !elementor.channels || !elementor.channels.editor) {
            setTimeout(initWhenReady, 100);
            return;
        }

        console.log('[EGP Simple] Elementor ready, initializing...');

        // Listen for control changes
        elementor.channels.editor.on('change', function(controlView, elementView) {
            if (!controlView || !controlView.model) return;
            
            var controlName = controlView.model.get('name');
            
            // Handle geo targeting controls
            if (controlName === 'egp_countries' || controlName === 'egp_geo_enabled') {
                console.log('[EGP Simple] Control changed:', controlName);
                setTimeout(function() {
                    saveRuleIfNeeded(elementView);
                }, 500);
            }
        });

        console.log('[EGP Simple] Event listeners registered');
    }

    function saveRuleIfNeeded(elementView) {
        if (!elementView || !elementView.model) return;

        var settings = elementView.model.get('settings');
        if (!settings) return;

        var enabled = settings.get('egp_geo_enabled') === 'yes';
        var countries = settings.get('egp_countries');
        
        if (!Array.isArray(countries)) {
            countries = countries ? [countries] : [];
        }

        if (!enabled || countries.length === 0) {
            console.log('[EGP Simple] Not saving - enabled:', enabled, 'countries:', countries);
            return;
        }

        var elementId = elementView.model.get('id');
        var elementType = elementView.model.get('elType') || 'section';
        
        var data = {
            action: 'egp_save_elementor_rule_enhanced',
            nonce: window.egpEditor ? egpEditor.nonce : '',
            element_id: elementId,
            element_type: elementType,
            countries: countries,
            priority: 50,
            active: true,
            title: elementType.charAt(0).toUpperCase() + elementType.slice(1) + ' ' + elementId
        };

        console.log('[EGP Simple] Saving rule:', data);

        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                console.log('[EGP Simple] Rule saved:', response.data);
            } else {
                console.log('[EGP Simple] Save failed:', response.data);
            }
        });
    }

    // Initialize
    $(document).ready(function() {
        initWhenReady();
    });

})(jQuery);