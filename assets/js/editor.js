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

    // Simplified utilities - focus on the working method
    function getCurrentSettings() {
        try {
            var panel = elementor.getPanelView().getCurrentPageView();
            if (panel && panel.model && typeof panel.model.get === 'function') {
                var s = panel.model.get('settings');
                if (s && typeof s.get === 'function' && typeof s.set === 'function') {
                    console.log('[EGP] Got panel and settings:', {
                        panelId: panel.model.get('id'),
                        panelType: panel.model.get('elType'),
                        hasSettings: !!s
                    });
                    return { panel: panel, settings: s };
                }
            }
        } catch (e) {
            console.log('[EGP] Error getting current settings:', e);
        }
        console.log('[EGP] No valid panel/settings found');
        return { panel: null, settings: null };
    }
    // Save rule when native countries select changes or enable toggle changes
    window.saveRuleFromCurrentPanel = function saveRuleFromCurrentPanel() {
        if (typeof elementor === 'undefined' || !elementor.getPanelView) { return; }
        var panel = elementor.getPanelView().getCurrentPageView();
        if (!panel || !panel.model) { return; }
        var settings = (panel.model && typeof panel.model.get === 'function') ? panel.model.get('settings') : null;
        if (!settings || typeof settings.get !== 'function') { return; }

        var rawEnabled = settings.get('egp_geo_enabled');
        var enabled = (rawEnabled === 'yes' || rawEnabled === '1' || rawEnabled === true);
        var countries = settings.get('egp_countries');
        
        // Ensure countries is an array
        if (!Array.isArray(countries)) {
            countries = countries ? [countries] : [];
        }

        var targetId = settings.get('egp_element_id') || panel.model.get('id') || '';
        var elementRefId = panel.model.get('id') || '';

        if (!enabled || !countries.length || !targetId) { 
            console.log('[EGP] Not saving rule - enabled:', enabled, 'countries:', countries, 'targetId:', targetId);
            return; 
        }

        var elType = panel.model.get('elType') || '';
        var targetType = (elType === 'widget') ? 'widget' : 'section';

        var documentId = 0;
        if (elementor.documents && elementor.documents.getCurrent) {
            var currentDoc = elementor.documents.getCurrent();
            if (currentDoc && currentDoc.id) { documentId = currentDoc.id; }
        }

        var url = (window.egpEditor && egpEditor.ajaxUrl) || (typeof ajaxurl !== 'undefined' ? ajaxurl : null);
        if (!url) { return; }
        
        var data = {
            action: 'egp_save_elementor_rule_enhanced',
            nonce: (window.egpEditor && egpEditor.nonce) || '',
            element_id: targetId,
            element_type: targetType,
            countries: countries,
            priority: 50,
            active: true,
            title: (targetType.charAt(0).toUpperCase() + targetType.slice(1)) + ' ' + targetId,
            element_ref_id: elementRefId,
            document_id: documentId
        };
        
        console.log('[EGP] Saving rule with data:', data);
        jQuery.post(url, data, function(response) {
            if (response.success) {
                console.log('[EGP] Rule saved successfully:', response.data);
            } else {
                console.log('[EGP] Rule save failed:', response.data);
            }
        }).fail(function() {
            console.log('[EGP] Rule save network error');
        });
    };

    // Bind to native select changes (SELECT2 controls)
    $(document).on('change', 'select[data-setting="egp_countries"]', function () {
        console.log('[EGP] Countries changed:', $(this).val());
        setTimeout(saveRuleFromCurrentPanel, 500); // Small delay to ensure Elementor model is updated
    });

    // Bind to enable toggle
    $(document).on('change.egp', 'input[data-setting="egp_geo_enabled"]', function () {
        console.log('[EGP] Geo enabled changed:', $(this).is(':checked'));
        setTimeout(saveRuleFromCurrentPanel, 500); // Small delay to ensure Elementor model is updated
    });

    // Legacy checkbox-based country control is deprecated; no JS syncing needed.

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

        // Legacy checkbox UI removed; no restore/fetch needed for countries

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

    // Restore country selection from Elementor model
    function restoreCountrySelection() { /* deprecated */ }

    // Fetch stored rule by element and apply to UI/model so it persists across reloads
    function fetchAndApplyCountries() { /* deprecated */ }

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
            // Persist into Elementor model so server saves use this stable ID
            try {
                var panel = (typeof elementor !== 'undefined' && elementor.getPanelView) ? elementor.getPanelView().getCurrentPageView() : null;
                if (panel && panel.model) {
                    var settings = panel.model.get('settings') || {};
                    settings.egp_element_id = elementId;
                    if (typeof settings.set === 'function') {
                        settings.set('egp_element_id', elementId);
                    } else {
                        panel.model.set('settings', settings);
                    }
                    if (typeof panel.model.setSetting === 'function') {
                        panel.model.setSetting('egp_element_id', elementId);
                    }
                    $elementIdField.trigger('input');
                }
            } catch (e) { }
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
                                // Use popup approach for enable toggle
                                var ctx = getCurrentSettings();
                                if (ctx.settings && ctx.panel) {
                                    var settings = ctx.panel.model.get('settings') || {};
                                    settings.egp_geo_enabled = $(this).is(':checked') ? 'yes' : '';
                                    ctx.panel.model.set('settings', settings);
                                }
                                // Do not auto-save custom rules here; native settings will be saved
                            });
                            // Bind element ID change
                            $(document).off('input.egp', 'input[name*="egp_element_id"]').on('input.egp', 'input[name*="egp_element_id"]', function () {
                                var ctx = getCurrentSettings();
                                if (ctx.settings && ctx.panel) {
                                    var settings = ctx.panel.model.get('settings') || {};
                                    settings.egp_element_id = ($(this).val() || '').trim();
                                    ctx.panel.model.set('settings', settings);
                                }
                            });
                            // Bind explicit save button if ever added
                        }, 200);
                    }
                });
                // No custom save hook; rely on Elementor save
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
            }, 100);
        }
    });

    // Persist current panel settings as a Geo Rule via AJAX
    function saveGeoRuleFromPanel() { /* deprecated */ }

})(jQuery);



