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

            // Get current panel and settings
            try {
                var panel = elementor.getPanelView().getCurrentPageView();
                if (!panel || !panel.model) {
                    console.log('[EGP Sync] No panel found');
                    return;
                }

                var settings = panel.model.get('settings');
                if (!settings) {
                    console.log('[EGP Sync] No settings found');
                    return;
                }

                // Update the hidden egp_countries setting and trigger change detection
                if (typeof settings.set === 'function') {
                    settings.set('egp_countries', selectedCountries);
                    console.log('[EGP Sync] Updated via settings.set()');
                } else {
                    // Fallback for older Elementor versions
                    var currentSettings = panel.model.get('settings');
                    currentSettings.egp_countries = selectedCountries;
                    panel.model.set('settings', currentSettings);
                    console.log('[EGP Sync] Updated via model.set()');
                }

                // Trigger Elementor's change detection to enable save button
                if (elementor && elementor.saver && elementor.saver.setFlagEditorChange) {
                    elementor.saver.setFlagEditorChange(true);
                }

                // Also trigger panel model change
                panel.model.trigger('change');

                // Auto-save rule after a delay
                setTimeout(function() {
                    autoSaveRule(panel);
                }, 500);

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
            var panel = elementor.getPanelView().getCurrentPageView();
            if (!panel || !panel.model) {
                return;
            }

            var settings = panel.model.get('settings');
            if (!settings) {
                return;
            }

            var storedCountries = settings.get('egp_countries');
            if (Array.isArray(storedCountries) && storedCountries.length > 0) {
                // Find the native select and set its value
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

    function autoSaveRule(panel) {
        if (!panel || !panel.model) {
            return;
        }

        var settings = panel.model.get('settings');
        if (!settings) {
            return;
        }

        var enabled = settings.get('egp_geo_enabled') === 'yes';
        var countries = settings.get('egp_countries') || [];

        if (!Array.isArray(countries)) {
            countries = countries ? [countries] : [];
        }

        if (!enabled || countries.length === 0) {
            console.log('[EGP Sync] Not saving - enabled:', enabled, 'countries:', countries);
            return;
        }

        // Use Elementor's internal ID (this matches the data-id attribute in the DOM)
        var elementId = panel.model.get('id');
        var elementType = panel.model.get('elType') || 'section';
        var priority = settings.get('egp_geo_priority') || 50;

        // Use custom label for display, but save Elementor's ID as the target
        var customLabel = settings.get('egp_element_id') || '';
        var title = customLabel || (elementType.charAt(0).toUpperCase() + elementType.slice(1) + ' ' + elementId);

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
                if (response.data && response.data.rule_id) {
                    settings.set('egp_rule_id', response.data.rule_id);
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
