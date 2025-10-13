/**
 * Elementor Geo Targeting - Unified Country Selection & Persistence
 * Handles syncing between native select and Elementor settings
 */

(function($) {
    'use strict';

    console.log('[EGP Sync] Script loaded');

    // Wait for Elementor to be ready
    function initWhenReady() {
        if (typeof elementor === 'undefined' || !elementor.channels || !elementor.channels.editor) {
            setTimeout(initWhenReady, 100);
            return;
        }

        console.log('[EGP Sync] Elementor ready, initializing');
        setupCountrySync();
    }

    function setupCountrySync() {
        // Handle country selection changes - target ALL possible select IDs
        $(document).on('change', 'select[id^="egp_countries_native"]', function() {
            var selectedCountries = $(this).val() || [];
            if (!Array.isArray(selectedCountries)) {
                selectedCountries = selectedCountries ? [selectedCountries] : [];
            }

            console.log('[EGP Sync] Countries selected:', selectedCountries);

            // Use panel current page view to avoid elementorFrontend dependency
            try {
                if (typeof elementor === 'undefined' || !elementor.getPanelView) {
                    console.log('[EGP Sync] Elementor panel not ready');
                    return;
                }
                var panelView = elementor.getPanelView().getCurrentPageView();
                if (!panelView || !panelView.model) {
                    console.log('[EGP Sync] No current element');
                    return;
                }
                var settings = panelView.model.get('settings');
                if (settings && typeof settings.set === 'function') {
                    settings.set('egp_countries', selectedCountries);
                } else {
                    // Fallback write
                    panelView.model.setSetting && panelView.model.setSetting('egp_countries', selectedCountries);
                }
                console.log('[EGP Sync] Updated element setting');

                // Trigger Elementor's change detection to enable save button
                if (window.$e && $e.internal) {
                    try { $e.internal('document/save/set-is-modified', { status: true }); } catch (_) { }
                    console.log('[EGP Sync] Set editor change flag');
                } else if (elementor && elementor.saver && elementor.saver.setFlagEditorChange) {
                    elementor.saver.setFlagEditorChange(true);
                    console.log('[EGP Sync] Set editor change flag');
                }

                // Auto-save rule after a delay
                setTimeout(function() {
                    autoSaveRule(panelView, selectedCountries);
                }, 700);

            } catch(e) {
                console.error('[EGP Sync] Error updating settings:', e);
            }
        });

        // Restore country selection when panel opens
        elementor.channels.editor.on('section:activated', function(sectionName) {
            if (sectionName === 'egp_geo_tools') {
                setTimeout(restoreCountrySelection, 200);
            }
        });

        // Also restore on initial panel load
        setTimeout(restoreCountrySelection, 500);
    }

    function restoreCountrySelection() {
        try {
            if (typeof elementor === 'undefined' || !elementor.getPanelView) {
                setTimeout(restoreCountrySelection, 200);
                return;
            }
            var panelView = null;
            try { panelView = elementor.getPanelView().getCurrentPageView && elementor.getPanelView().getCurrentPageView(); } catch (err) {
                setTimeout(restoreCountrySelection, 250);
                return;
            }
            if (!panelView || !panelView.model) { setTimeout(restoreCountrySelection, 200); return; }
            var settings = panelView.model.get('settings');
            var storedCountries = settings && typeof settings.get === 'function' ? settings.get('egp_countries') : [];
            if (Array.isArray(storedCountries) && storedCountries.length > 0) {
                var $select = $('select[id^="egp_countries_native"]');
                if ($select.length) {
                    $select.val(storedCountries);
                    console.log('[EGP Sync] Restored countries:', storedCountries);
                }
            }
        } catch(e) {
            console.error('[EGP Sync] Error restoring selection:', e);
        }
    }

    function autoSaveRule(viewOrPanel, countriesOverride) {
        var model = viewOrPanel && viewOrPanel.model ? viewOrPanel.model : null;
        if (!model) {
            return;
        }

        var settingsObj = (model.get && model.get('settings')) || null;
        var enabled = (model.getSetting && model.getSetting('egp_geo_enabled') === 'yes')
            || (settingsObj && settingsObj.get && settingsObj.get('egp_geo_enabled') === 'yes')
            // For popups, also respect page-level key if present
            || (settingsObj && settingsObj.get && settingsObj.get('egp_enable_geo_targeting') === 'yes');
        var countries = (Array.isArray(countriesOverride) && countriesOverride.length) ? countriesOverride
            : (model.getSetting && model.getSetting('egp_countries'))
            || (model.get && model.get('settings') && model.get('settings').get && model.get('settings').get('egp_countries')) || [];

        if (!Array.isArray(countries)) {
            countries = countries ? [countries] : [];
        }

        // In popup editor, allow save when countries chosen even if toggle not yet reflected
        var isPopupDoc = !!(window.egpEditor && egpEditor.isPopup);
        if ((!enabled && !isPopupDoc) || countries.length === 0) {
            console.log('[EGP Sync] Not saving - enabled:', enabled, 'countries:', countries);
            return;
        }

        // Use Elementor's internal ID (this matches the data-id attribute in the DOM)
        var elementId = model.get('id');
        var elementType = model.get('elType') || 'section';
        // For popups, target the popup document ID and type=popup so PHP creates a popup rule
        if (isPopupDoc && window.egpEditor && egpEditor.documentId) {
            elementId = egpEditor.documentId;
            elementType = 'popup';
        }
        var priority = (model.getSetting && model.getSetting('egp_geo_priority'))
            || (model.get && model.get('settings') && model.get('settings').get && model.get('settings').get('egp_geo_priority')) || 50;

        // Use custom label for display, but save Elementor's ID as the target
        var customLabel = (model.getSetting && model.getSetting('egp_element_id'))
            || (model.get && model.get('settings') && model.get('settings').get && model.get('settings').get('egp_element_id')) || '';
        // Build a consistent title: "Rule Name (Element ID)"
        var baseName = customLabel && String(customLabel).trim().length ? customLabel.trim() : (elementType.charAt(0).toUpperCase() + elementType.slice(1) + ' ' + elementId);
        var title = baseName + ' (' + elementId + ')';

        var data = {
            action: 'egp_save_elementor_rule_enhanced',
            nonce: (window.egpEditor && egpEditor.nonce) || '',
            element_id: elementId,
            element_type: elementType,
            countries: countries,
            priority: priority,
            active: true,
            title: title
        };

        console.log('[EGP Sync] Auto-saving rule:', data);
        console.log('[EGP Sync] Elementor ID (data-id):', elementId, '| Custom Label:', customLabel || '(none)');

        var ajaxUrl = (window.egpEditor && egpEditor.ajaxUrl) || ajaxurl;
        if (!ajaxUrl) {
            console.log('[EGP Sync] No AJAX URL available');
            return;
        }

        $.post(ajaxUrl, data, function(response) {
            if (response.success) {
                console.log('[EGP Sync] Rule saved successfully:', response.data);
                
                // Show brief success indicator
                showSaveIndicator('success');

                // Store the rule ID for future updates
                if (response.data && response.data.rule_id && model && model.setSetting) {
                    model.setSetting('egp_rule_id', response.data.rule_id);
                    console.log('[EGP Sync] Stored rule_id:', response.data.rule_id);
                }

                // Trigger custom event for admin panel refresh
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        type: 'egp_rule_saved',
                        rule_id: response.data && response.data.rule_id,
                        element_id: elementId
                    }, '*');
                }
            } else {
                console.log('[EGP Sync] Save failed:', response.data);
                showSaveIndicator('error');
            }
        }).fail(function() {
            console.log('[EGP Sync] Network error during save');
            showSaveIndicator('error');
        });
    }

    function showSaveIndicator(status) {
        // Create or update a save indicator in the geo panel
        var $indicator = $('#egp-save-indicator');
        if (!$indicator.length) {
            $indicator = $('<div id="egp-save-indicator" style="position:fixed;top:50px;right:20px;padding:10px 15px;border-radius:4px;color:white;font-size:12px;z-index:99999;"></div>');
            $('body').append($indicator);
        }

        if (status === 'success') {
            $indicator.css('background-color', '#46b450').text('✓ Geo rule saved').fadeIn();
        } else {
            $indicator.css('background-color', '#dc3232').text('✗ Save failed').fadeIn();
        }

        setTimeout(function() {
            $indicator.fadeOut();
        }, 2000);
    }

    // Initialize when document is ready
    $(document).ready(initWhenReady);

})(jQuery);
