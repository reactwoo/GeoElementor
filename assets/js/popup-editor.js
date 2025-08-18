/**
 * Elementor Geo Popup - Popup Editor JavaScript
 * 
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    // Wait for Elementor to be ready
    $(document).ready(function () {

        // Check if we're in the Elementor editor
        if (typeof elementor === 'undefined') {
            return;
        }

        // Initialize when Elementor is ready
        elementor.on('editor:init', function () {
            initGeoPopupEditor();
        });

        // Also initialize if editor context already active (Elementor 3.x safe)
        try {
            var isEditor = false;
            if (window.elementor && typeof elementor.isEditMode === 'function') {
                isEditor = elementor.isEditMode();
            } else if (window.elementorCommon && elementorCommon.config && elementorCommon.config.isEditor) {
                isEditor = true;
            } else if (window.elementor && elementor.channels && elementor.channels.editor) {
                isEditor = true;
            }
            if (isEditor) {
                initGeoPopupEditor();
            }
        } catch (e) { }

    });

    /**
     * Initialize the geo popup editor functionality
     */
    function initGeoPopupEditor() {

        // Wait for popup editor to be available
        var checkPopupEditor = setInterval(function () {
            if (elementor.channels.editor && elementor.channels.editor.trigger) {
                clearInterval(checkPopupEditor);
                setupGeoPopupEditor();
            }
        }, 100);

    }

    /**
     * Setup the geo popup editor functionality
     */
    function setupGeoPopupEditor() {

        // Listen for popup settings changes
        elementor.channels.editor.on('change:popup:settings', function (model) {
            handlePopupSettingsChange(model);
        });

        // Listen for popup save
        elementor.channels.editor.on('popup:save', function () {
            saveGeoPopupSettings();
        });

        // Initialize existing settings
        initializeExistingSettings();

        // Add custom controls to the popup editor
        addCustomControls();

        // Enhance Publish Settings → Advanced Rules with a Geo entry point
        installAdvancedRulesEntryPoint();

    }

    /**
     * Handle popup settings changes
     */
    function handlePopupSettingsChange(model) {
        var popupId = model.get('ID');
        var geoEnabled = model.get('settings').get('egp_enable_geo_targeting');
        var countries = model.get('settings').get('egp_countries');
        var fallback = model.get('settings').get('egp_fallback_behavior');

        // Update the geo targeting section visibility
        updateGeoSectionVisibility(geoEnabled);

        // Save settings to our database
        if (popupId) {
            saveGeoPopupSettings();
        }
    }

    /**
     * Update geo section visibility based on enabled state
     */
    function updateGeoSectionVisibility(enabled) {
        var $geoSection = $('.elementor-control-egp_geo_targeting_section');

        if (enabled === 'yes') {
            $geoSection.show();
        } else {
            $geoSection.hide();
        }
    }

    /**
     * Add custom controls to the popup editor
     */
    function addCustomControls() {

        // Add help tooltips
        $('.elementor-control-egp_countries .elementor-control-description').append(
            '<span class="egp-help-tip" title="Select the countries where this popup should be displayed. Leave empty to show to all countries.">?</span>'
        );

        $('.elementor-control-egp_fallback_behavior .elementor-control-description').append(
            '<span class="egp-help-tip" title="Choose what happens when a visitor\'s country doesn\'t match the selected countries.">?</span>'
        );

        // Add country search functionality
        var $countriesSelect = $('.elementor-control-egp_countries select');
        if ($countriesSelect.length) {
            $countriesSelect.select2({
                placeholder: 'Select countries...',
                allowClear: true,
                width: '100%'
            });
        }

        // Add quick-fill Preferred Countries button when geo targeting is enabled
        var $countriesControl = $('.elementor-control-egp_countries');
        if ($countriesControl.length && Array.isArray(window.egpPopupEditor && egpPopupEditor.preferredCountries)) {
            var $btn = $('<button type="button" class="button button-secondary" style="margin-top:8px;">' + (egpPopupEditor.strings && egpPopupEditor.strings.usePreferred || 'Use Preferred Countries') + '</button>');
            $btn.on('click', function () {
                var preferred = egpPopupEditor.preferredCountries || [];
                var $select = $countriesControl.find('select');
                $select.val(preferred).trigger('change');
            });
            $countriesControl.append($btn);
        }

        // Auto-prefill countries with Preferred on enable
        var $enableSwitch = $('.elementor-control-egp_enable_geo_targeting input[type="checkbox"]');
        $enableSwitch.on('change', function () {
            var on = $(this).is(':checked');
            if (on && Array.isArray(egpPopupEditor.preferredCountries)) {
                var $select = $('.elementor-control-egp_countries select');
                if ($select && (!$select.val() || $select.val().length === 0)) {
                    $select.val(egpPopupEditor.preferredCountries).trigger('change');
                }
            }
        });

        // Add validation
        addValidation();

    }

    /**
     * Install a secondary entry point in Publish Settings → Advanced Rules
     * Adds a "Show on country" row with a globe icon that toggles EGP and focuses the control
     */
    function installAdvancedRulesEntryPoint() {
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                if (!m.addedNodes) return;
                $(m.addedNodes).each(function () {
                    var $node = $(this);
                    // Detect Elementor Publish Settings modal
                    if (
                        $node.is('.elementor-publish__modal, .elementor-publish__dropdown, .elementor-conditions-modal, .dialog-lightbox-widget') ||
                        $node.find('.elementor-publish__modal, .elementor-publish__dropdown, .elementor-conditions-modal, .dialog-lightbox-widget').length
                    ) {
                        tryInjectAdvancedRule();
                    }
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });

        // Also observe main editor iframe if present (Elementor often renders overlays in different roots)
        setTimeout(function () {
            var iframe = document.querySelector('#elementor-preview-iframe');
            if (iframe && iframe.contentWindow && iframe.contentDocument) {
                try {
                    new MutationObserver(function () { tryInjectAdvancedRule(); })
                        .observe(iframe.contentDocument.body, { childList: true, subtree: true });
                } catch (e) { }
            }
        }, 500);

        // Also try immediately in case modal already open
        setTimeout(tryInjectAdvancedRule, 300);
        setTimeout(tryInjectAdvancedRule, 1200);
    }

    function tryInjectAdvancedRule() {
        // Helper: collect top document and same-origin iframes
        function getSearchRoots() {
            var roots = [document];
            var iframes = document.querySelectorAll('iframe');
            iframes.forEach(function (ifr) {
                try {
                    if (ifr.contentDocument && ifr.contentDocument.body) { roots.push(ifr.contentDocument); }
                } catch (e) { }
            });
            return roots;
        }

        // Find the Advanced Rules controls container reliably
        function getControlsRoot() {
            var $root = $();
            getSearchRoots().forEach(function (doc) {
                $root = $root.add($('.elementor-popup-timing__controls', doc));
            });
            if ($root.length) { return $root.filter(':visible').first(); }
            // Anchor-based fallback: locate a known rule label and use its parent list
            var anchors = [
                'Show after X page views',
                'Show after X sessions',
                'Show up to X times',
                'Show when arriving from specific URL',
                'Show when arriving from',
                'Hide for logged in users',
                'Show on devices',
                'Show on browsers',
                'Schedule date and time'
            ];
            var $candidate = $();
            getSearchRoots().forEach(function (doc) {
                anchors.forEach(function (t) {
                    if ($candidate.length) { return; }
                    var $a = $(doc).find(':contains("' + t + '")').filter(function () { return $(this).is(':visible'); }).first();
                    if ($a.length) {
                        var $row = $a.closest('.elementor-repeater-row, li, .elementor-requirement, .elementor-rule, .e-advanced-rule');
                        if ($row.length && $row.parent().length) {
                            $candidate = $row.parent();
                        }
                    }
                });
            });
            return $candidate.filter(':visible').first();
        }
        // Find Advanced Rules list container (Elementor markup varies by version)
        var selectors = [
            '.elementor-publish__modal .elementor-publish__requirements',
            '.elementor-publish__modal .elementor-publish__rules',
            '.elementor-publish__modal .elementor-advanced-rules',
            '.elementor-conditions-modal .elementor-conditions-list',
            '.dialog-lightbox-widget .elementor-publish__requirements',
            '.dialog-lightbox-widget .elementor-conditions-list',
            '.elementor-conditions-modal .e-conditions__rules',
            '.elementor-publish__modal .e-advanced-rules',
            // Elementor Pro Advanced Rules container in some versions
            '.elementor-publish__modal .elementor-popup-timing__controls',
            '.dialog-lightbox-widget .elementor-popup-timing__controls',
            '.elementor-popup-timing__controls'
        ];
        var $advancedLists = $();
        getSearchRoots().forEach(function (rootDoc) {
            $advancedLists = $advancedLists.add($(selectors.join(','), rootDoc));
        });
        var $controlsRoot = getControlsRoot();
        if (!$advancedLists.length) {
            // Text-anchor fallback for Elementor Pro 3.29.x – look for known rule labels
            var $modals = $();
            getSearchRoots().forEach(function (rootDoc) {
                $modals = $modals.add($('.elementor-publish__modal, .elementor-conditions-modal, .dialog-lightbox-widget', rootDoc));
            });
            if (!$modals.length) return;
            var $anchorLabel = $modals.find(':contains("Show on devices"), :contains("Show on browsers"), :contains("Hide for logged in users")').filter(function () {
                // limit to visible elements
                return $(this).is(':visible');
            }).first();
            if ($anchorLabel.length) {
                var $rowCandidate = $anchorLabel.closest('li, .elementor-conditions__item, .elementor-conditions-list__item, .e-advanced-rule, .elementor-requirement, .elementor-rule, div');
                if ($rowCandidate.length) {
                    var $wrap = $('<div class="egp-advanced-rules-wrap"></div>');
                    $rowCandidate.before($wrap);
                    $advancedLists = $wrap; // inject into our wrap placed in the list
                }
            }
            // Final fallback: inject a container near the modal footer/top even if list not found
            if (!$advancedLists.length) {
                var $visibleModal = $modals.filter(':visible').first();
                if ($visibleModal.length) {
                    var $wrap2 = $('<div class="egp-advanced-rules-wrap"></div>');
                    var $footer = $visibleModal.find('.elementor-publish__footer, .dialog-buttons-wrapper, .e-footer').first();
                    if ($footer.length) {
                        $wrap2.insertBefore($footer);
                    } else {
                        $visibleModal.children().first().before($wrap2);
                    }
                    $advancedLists = $wrap2;
                } else {
                    return;
                }
            }
        }

        // If already injected inside a correct container, do nothing
        if ($advancedLists.find('.egp-advanced-rule').length && $controlsRoot.length) {
            // ensure correct placement at top of controls root
            var $existingInContainer = $controlsRoot.find('.egp-advanced-rule');
            if ($existingInContainer.length) { return; }
        }
        // If advanced rule exists elsewhere (e.g., top of modal), relocate into controls root or target container
        var $existingLoose = $('.egp-advanced-rule').first();

        var isEnabled = $('.elementor-control-egp_enable_geo_targeting input[type="checkbox"]').is(':checked');

        // Build a lightweight row that matches Elementor's list look-and-feel
        var $row = $existingLoose.length ? $existingLoose.detach() : $('<div class="egp-advanced-rule" style="display:flex;align-items:center;justify-content:space-between;border:1px solid #e5e7eb;border-radius:6px;padding:10px 12px;margin:10px 0;">\
            <div style="display:flex;align-items:center;gap:10px;">\
                <i class="eicon-globe" style="font-size:16px;opacity:.75;"></i>\
                <div>\
                    <div style="font-weight:600;">Show on country</div>\
                    <div style="font-size:12px;opacity:.7;">Limit this popup to specific countries</div>\
                </div>\
            </div>\
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">\
                <span style="font-size:12px;opacity:.8;">'+ (isEnabled ? 'On' : 'Off') + '</span>\
                <input type="checkbox" '+ (isEnabled ? 'checked' : '') + '/>\
            </label>\
        </div>');

        $row.find('input[type="checkbox"]').on('change', function () {
            var on = $(this).is(':checked');
            var $switch = $('.elementor-control-egp_enable_geo_targeting input[type="checkbox"]');
            if ($switch.length) {
                if ($switch.is(':checked') !== on) {
                    $switch.prop('checked', on).trigger('change');
                }
            }
            // Focus countries control to guide the user
            if (on) {
                var $countries = $('.elementor-control-egp_countries');
                if ($countries.length) {
                    $('html,body').animate({ scrollTop: $countries.offset().top - 80 }, 200);
                    $countries.addClass('egp-pulse');
                    setTimeout(function () { $countries.removeClass('egp-pulse'); }, 1200);
                }
            }
            $(this).closest('label').find('span').text(on ? 'On' : 'Off');
        });

        // Insert the row at the top of Advanced Rules; prefer rules container with list items
        var $target = ($controlsRoot && $controlsRoot.length) ? $controlsRoot : $advancedLists.filter(function () {
            var $el = $(this);
            return $el.find('> *').length > 0;
        }).first();
        if (!$target.length) { $target = $advancedLists.first(); }
        $target.prepend($row);
    }

    /**
     * Add validation to the geo targeting controls
     */
    function addValidation() {

        // Validate countries selection
        $('.elementor-control-egp_countries select').on('change', function () {
            var selectedCountries = $(this).val();
            var $description = $(this).closest('.elementor-control').find('.elementor-control-description');

            if (selectedCountries && selectedCountries.length > 0) {
                $description.html('Popup will be shown to visitors from: ' + selectedCountries.join(', '));
            } else {
                $description.html('No countries selected. Popup will be shown to all visitors (unless restricted by global preferred-countries setting for untargeted popups).');
            }
        });

        // Validate fallback behavior
        $('.elementor-control-egp_fallback_behavior select').on('change', function () {
            var value = $(this).val();
            var $description = $(this).closest('.elementor-control').find('.elementor-control-description');

            switch (value) {
                case 'inherit':
                    $description.html('Will use the global fallback behavior set in plugin settings.');
                    break;
                case 'show_to_all':
                    $description.html('All visitors will see this popup regardless of country.');
                    break;
                case 'show_to_none':
                    $description.html('No popup will be shown when country doesn\'t match.');
                    break;
                case 'show_default':
                    $description.html('The default popup will be shown when country doesn\'t match.');
                    break;
            }
        });

    }

    /**
     * Initialize existing settings
     */
    function initializeExistingSettings() {

        var popupId = getCurrentPopupId();
        if (!popupId) {
            return;
        }

        // Load existing geo settings
        loadExistingGeoSettings(popupId);

    }

    /**
     * Get current popup ID
     */
    function getCurrentPopupId() {
        // Try to get popup ID from various sources
        var popupId = null;

        // Method 1: From URL
        var urlMatch = window.location.href.match(/popup_id=(\d+)/);
        if (urlMatch) {
            popupId = urlMatch[1];
        }

        // Method 2: From Elementor data
        if (!popupId && elementor.documents && elementor.documents.getCurrentDocument) {
            var currentDoc = elementor.documents.getCurrentDocument();
            if (currentDoc && currentDoc.model) {
                popupId = currentDoc.model.get('ID');
            }
        }

        // Method 3: From DOM
        if (!popupId) {
            var $popupIdInput = $('input[name="post_ID"]');
            if ($popupIdInput.length) {
                popupId = $popupIdInput.val();
            }
        }

        return popupId;
    }

    /**
     * Load existing geo settings for a popup
     */
    function loadExistingGeoSettings(popupId) {

        $.post(egpPopupEditor.ajaxUrl, {
            action: 'egp_get_popup_countries',
            popup_id: popupId,
            nonce: egpPopupEditor.nonce
        }, function (response) {
            if (response.success) {
                applyExistingSettings(response.data);
            }
        }).fail(function () {
            console.log('Failed to load existing geo settings');
        });

    }

    /**
     * Apply existing settings to the form
     */
    function applyExistingSettings(data) {

        // Update the enable/disable switch
        var $enableSwitch = $('.elementor-control-egp_enable_geo_targeting input[type="checkbox"]');
        if ($enableSwitch.length) {
            if (data.enabled === 'yes') {
                $enableSwitch.prop('checked', true).trigger('change');
            } else {
                $enableSwitch.prop('checked', false).trigger('change');
            }
        }

        // Update countries selection
        var $countriesSelect = $('.elementor-control-egp_countries select');
        if ($countriesSelect.length && data.countries) {
            $countriesSelect.val(data.countries).trigger('change');
        }

        // Update fallback behavior
        var $fallbackSelect = $('.elementor-control-egp_fallback_behavior select');
        if ($fallbackSelect.length && data.fallback_behavior) {
            $fallbackSelect.val(data.fallback_behavior).trigger('change');
        }

    }

    /**
     * Save geo popup settings
     */
    function saveGeoPopupSettings() {

        var popupId = getCurrentPopupId();
        if (!popupId) {
            return;
        }

        // Get current values from the form
        var geoEnabled = $('.elementor-control-egp_enable_geo_targeting input[type="checkbox"]').is(':checked') ? 'yes' : 'no';
        var countries = $('.elementor-control-egp_countries select').val() || [];
        var fallback = $('.elementor-control-egp_fallback_behavior select').val() || 'inherit';

        // Save to our database
        $.post(egpPopupEditor.ajaxUrl, {
            action: 'egp_save_popup_countries',
            popup_id: popupId,
            enabled: geoEnabled,
            countries: countries,
            fallback: fallback,
            nonce: egpPopupEditor.nonce
        }, function (response) {
            if (response.success) {
                showSaveSuccess();
            } else {
                showSaveError(response.data);
            }
        }).fail(function () {
            showSaveError('Failed to save settings. Please try again.');
        });

    }

    /**
     * Show save success message
     */
    function showSaveSuccess() {
        var $message = $('<div class="egp-save-message egp-success">' + egpPopupEditor.strings.saved + '</div>');
        $('.elementor-control-egp_geo_targeting_section').prepend($message);

        setTimeout(function () {
            $message.fadeOut();
        }, 3000);
    }

    /**
     * Show save error message
     */
    function showSaveError(message) {
        var $message = $('<div class="egp-save-message egp-error">' + message + '</div>');
        $('.elementor-control-egp_geo_targeting_section').prepend($message);

        setTimeout(function () {
            $message.fadeOut();
        }, 5000);
    }

    /**
     * Add custom CSS for better styling
     */
    function addCustomCSS() {
        var css = `
            .egp-save-message {
                padding: 8px 12px;
                margin: 10px 0;
                border-radius: 4px;
                font-weight: 500;
            }
            .egp-save-message.egp-success {
                background-color: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            .egp-save-message.egp-error {
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            .egp-help-tip {
                display: inline-block;
                width: 16px;
                height: 16px;
                line-height: 16px;
                text-align: center;
                background-color: #0073aa;
                color: white;
                border-radius: 50%;
                font-size: 11px;
                font-weight: bold;
                cursor: help;
                margin-left: 5px;
            }
            .elementor-control-egp_geo_targeting_section {
                border-top: 1px solid #ddd;
                padding-top: 20px;
                margin-top: 20px;
            }
        `;

        var $style = $('<style>').text(css);
        $('head').append($style);
    }

    // Add custom CSS when document is ready
    $(document).ready(function () {
        addCustomCSS();
    });

})(jQuery);



