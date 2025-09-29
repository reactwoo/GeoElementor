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

        // Initialize native select values when panel loads
        function initializeNativeSelect() {
            var $nativeSelect = $('#egp_countries_native_widget, #egp_countries_native_container, #egp_countries_native_section');
            if ($nativeSelect.length) {
                var panel = elementor.getPanelView().getCurrentPageView();
                if (panel && panel.model) {
                    var settings = panel.model.get('settings');
                    if (settings) {
                        var storedCountries = settings.get('egp_countries');
                        if (Array.isArray(storedCountries) && storedCountries.length > 0) {
                            $nativeSelect.val(storedCountries);
                            console.log('[EGP Simple] Initialized native select with:', storedCountries);
                        }
                    }
                }
            }
        }

        $(document).on('elementor/popup/show', function () {
            setTimeout(initializeNativeSelect, 100);
        });

        // Also initialize when element is selected
        elementor.channels.editor.on('change', function (controlView, elementView) {
            if (controlView && controlView.model && controlView.model.get('name') === 'egp_geo_enabled') {
                setTimeout(initializeNativeSelect, 200);
            }
        });

        // Also bind change to our native multi-select and mirror into hidden setting
        $(document).on('change.egp', '#egp_countries_native_widget, #egp_countries_native_container, #egp_countries_native_section', function () {
            try {
                console.log('[EGP Simple] Native select changed');
                var panel = elementor.getPanelView().getCurrentPageView();
                if (!panel || !panel.model) {
                    console.log('[EGP Simple] No panel or model found');
                    return;
                }
                var settings = panel.model.get('settings');
                if (!settings) {
                    console.log('[EGP Simple] No settings found');
                    return;
                }
                var vals = $(this).val();
                console.log('[EGP Simple] Selected countries:', vals);
                if (!Array.isArray(vals)) { vals = vals ? [vals] : []; }
                if (typeof settings.set === 'function') {
                    settings.set('egp_countries', vals);
                    console.log('[EGP Simple] Updated settings via set()');
                } else {
                    panel.model.set('settings', Object.assign({}, settings, { egp_countries: vals }));
                    console.log('[EGP Simple] Updated settings via model.set()');
                }
                setTimeout(function () { saveRuleIfNeeded(panel); }, 300);
            } catch (e) { console.log('[EGP Simple] mirror error', e); }
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
        
        // Get the custom Element ID if set, otherwise use the auto-generated one
        var customElementId = settings.get('egp_element_id') || elementId;
        var ruleTitle = customElementId ? customElementId : (elementType.charAt(0).toUpperCase() + elementType.slice(1) + ' ' + elementId);

        var data = {
            action: 'egp_save_elementor_rule_enhanced',
            nonce: window.egpEditor ? egpEditor.nonce : '',
            element_id: customElementId,
            element_type: elementType,
            countries: countries,
            priority: 50,
            active: true,
            title: ruleTitle
        };

        console.log('[EGP Simple] Saving rule:', data);

        var ajaxUrl = (window.egpEditor && egpEditor.ajaxUrl) || (typeof ajaxurl !== 'undefined' ? ajaxurl : null);
        if (!ajaxUrl) {
            console.log('[EGP Simple] No AJAX URL available');
            return;
        }

        $.post(ajaxUrl, data, function (response) {
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