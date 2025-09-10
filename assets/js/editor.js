/**
 * Elementor Editor Integration for Geo Targeting
 * Adds geo targeting controls to the Advanced tab
 */

(function ($) {
    'use strict';

    var GeoElementorEditor = {
        init: function () {
            this.bindEvents();
            try { if (window.console && console.log) console.log('[EGP] Editor integration loaded'); } catch (e) { }
        },

        bindEvents: function () {
            // Wait for Elementor to be ready
            var self = this;
            function ready() {
                if (typeof elementor !== 'undefined' && elementor.hooks && elementor.channels && elementor.channels.editor) {
                    self.addGeoControls();
                    return;
                }
                setTimeout(ready, 100);
            }
            ready();
        },

        addGeoControls: function () {
            var self = this;
            // Log that PHP controls should be handling this now
            if (window.console && console.log) {
                console.log('[EGP] PHP controls registration handles geo targeting - JS panel injection removed');
            }
            // No longer needed - PHP controls manager handles everything
        },

        // addGeoPanel function removed - PHP controls manager handles this now

        // initializeCountrySelect removed - PHP controls handle this now

        // bindGeoEvents removed - PHP controls handle this now

        // loadExistingSettings and saveGeoSettings removed - PHP controls handle this now

        // saveGeoRuleToDatabase and removeGeoRuleFromDatabase removed - PHP controls handle this now
    };

    // Initialize when document is ready
    $(document).ready(function () {
        GeoElementorEditor.init();
    });

    // Handle Elementor controls synchronization
    $(document).on('change', '#egp_countries_native', function () {
        var selectedCountries = [];
        $(this).find('option:selected').each(function () {
            selectedCountries.push($(this).val());
        });
        // Update the hidden JSON store
        var $store = $(this).closest('.elementor-control').find('input[name*="egp_geo_countries_store"]');
        if ($store.length) {
            $store.val(JSON.stringify(selectedCountries));
        }
        if (window.console && console.log) {
            console.log('[EGP] Countries updated:', selectedCountries);
        }
        // Attempt to persist immediately
        try { saveGeoRuleFromPanel(); } catch (e) { }
    });

    // Handle copy element ID button
    $(document).on('click', '#egp-copy-element-id', function (e) {
        e.preventDefault();
        var elementId = $('#egp-element-id-display').text();
        if (elementId && elementId !== '—') {
            try {
                navigator.clipboard.writeText(elementId);
                // Show brief success feedback
                var $btn = $(this);
                var originalText = $btn.text();
                $btn.text('Copied!');
                setTimeout(function () {
                    $btn.text(originalText);
                }, 1000);
            } catch (err) {
                // Fallback for older browsers
                var textArea = document.createElement('textarea');
                textArea.value = elementId;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
            }
        }
    });

    // Initialize country selector and element ID when controls are ready
    function initializeGeoControls() {
        // Initialize country selector with stored values
        var $countrySelect = $('#egp_countries_native');
        var $countryStore = $('input[name*="egp_geo_countries_store"]');

        if ($countrySelect.length && $countryStore.length) {
            try {
                var storedCountries = JSON.parse($countryStore.val() || '[]');
                if (storedCountries.length > 0) {
                    $countrySelect.val(storedCountries);
                }
            } catch (e) {
                if (window.console && console.log) {
                    console.log('[EGP] Error parsing stored countries:', e);
                }
            }
        }

        // Initialize element ID display
        var $display = $('#egp-element-id-display');
        if ($display.length && $display.text() === '—') {
            // Try to get element ID from Elementor
            if (typeof elementor !== 'undefined') {
                var panel = elementor.getPanelView().getCurrentPageView();
                if (panel && panel.model) {
                    var elementId = panel.model.get('id');
                    if (elementId) {
                        $display.text(elementId);
                    }
                }
            }
        }
    }

    // Auto-generate Element ID when field is empty
    function autoGenerateElementId() {
        var $elementIdField = $('input[name*="egp_element_id"]');
        if ($elementIdField.length && (!$elementIdField.val() || $elementIdField.val().trim() === '')) {
            // Generate a unique ID based on current timestamp and random number
            var timestamp = Date.now();
            var random = Math.floor(Math.random() * 1000);
            var elementId = 'geo_' + timestamp + '_' + random;
            $elementIdField.val(elementId);
            console.log('[EGP] Auto-generated Element ID:', elementId);
        }
    }

    // Update element ID display when Elementor loads
    $(document).on('elementor:init', function () {
        setTimeout(function () {
            // Try to get the current element ID from Elementor
            if (typeof elementor !== 'undefined' && elementor.channels && elementor.channels.editor) {
                elementor.channels.editor.on('section:activated', function (sectionName, editor) {
                    if (sectionName === 'section_advanced') {
                        setTimeout(function () {
                            initializeGeoControls();
                            // Auto-generate Element ID after controls are initialized
                            setTimeout(autoGenerateElementId, 300);
                            // Bind save on toggle change
                            $(document).off('change.egp', 'input[name*="egp_geo_enabled"]').on('change.egp', 'input[name*="egp_geo_enabled"]', function () {
                                try { saveGeoRuleFromPanel(); } catch (e) { }
                            });
                            // Bind explicit save button if ever added
                        }, 200);
                    }
                });
                // Also try to save on editor document save
                elementor.channels.editor.on('saved', function () { try { saveGeoRuleFromPanel(); } catch (e) { } });
            }
            // Also initialize on panel open
            setTimeout(function () {
                initializeGeoControls();
                setTimeout(autoGenerateElementId, 300);
            }, 500);
        }, 1000);
    });

    // Re-initialize when controls might be dynamically loaded
    $(document).on('DOMNodeInserted', function (e) {
        if ($(e.target).hasClass('elementor-control-egp_geo_tools')) {
            setTimeout(function () {
                initializeGeoControls();
                setTimeout(autoGenerateElementId, 200);
                try { saveGeoRuleFromPanel(); } catch (e) { }
            }, 100);
        }
    });

    // Persist current panel settings as a Geo Rule via AJAX
    function saveGeoRuleFromPanel() {
        if (typeof elementor === 'undefined' || !elementor.getPanelView) { return; }
        var panel = elementor.getPanelView().getCurrentPageView();
        if (!panel || !panel.model) { return; }
        var elType = panel.model.get('elType') || '';
        var targetType = (elType === 'widget') ? 'widget' : 'section';
        var $root = jQuery('.elementor-control-egp_geo_tools');
        if (!$root.length) { return; }
        var enabled = $root.find('input[name*="egp_geo_enabled"]').is(':checked');
        var countriesStore = $root.find('input[name*="egp_geo_countries_store"]').val() || '[]';
        var countries = [];
        try { countries = JSON.parse(countriesStore); } catch (e) { countries = []; }
        var targetId = '';
        var $idField = jQuery('input[name*="egp_element_id"]');
        if ($idField.length && $idField.val()) {
            targetId = $idField.val().trim();
        } else if (panel.model.get('id')) {
            targetId = panel.model.get('id');
        }
        if (!enabled || !countries.length || !targetId) { return; }

        var data = {
            action: 'egp_save_elementor_geo_rule',
            nonce: (window.egpEditor && egpEditor.nonce) || '',
            target_type: targetType,
            target_id: targetId,
            countries: countries,
            priority: 50,
            active: true,
            title: (targetType.charAt(0).toUpperCase() + targetType.slice(1)) + ' ' + targetId,
            element_type: targetType
        };
        jQuery.post((window.egpEditor && egpEditor.ajaxUrl) || ajaxurl, data)
            .done(function (res) { try { if (window.console && console.log) console.log('[EGP] Saved geo rule from builder', res); } catch (e) { } })
            .fail(function (err) { try { if (window.console && console.warn) console.warn('[EGP] Failed to save geo rule', err); } catch (e) { } });
    }

})(jQuery);
