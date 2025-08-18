/**
 * Elementor Geo Popup - Global Settings JavaScript
 *
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        if (typeof elementor === 'undefined') {
            return;
        }

        // Initialize global settings functionality
        initGlobalSettings();
    });

    /**
     * Initialize global settings functionality
     */
    function initGlobalSettings() {
        // Listen for Elementor editor initialization
        if (window.elementor && elementor.on) {
            elementor.on('editor:init', function () {
                setupGlobalSettings();
            });
        }

        // Also initialize if already in editor context (safe across versions)
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
                setupGlobalSettings();
            }
        } catch (e) { }
    }

    /**
     * Setup global settings functionality
     */
    function setupGlobalSettings() {
        // Add geo-targeting controls to global elements
        addGeoControlsToGlobals();

        // Listen for global element changes
        listenForGlobalChanges();

        // Initialize existing global settings
        initializeExistingGlobalSettings();
    }

    /**
     * Add geo controls to global elements
     */
    function addGeoControlsToGlobals() {
        // Add geo-targeting section to global widgets
        elementor.hooks.addAction('panel/open_editor/widget', function (panel, model, view) {
            if (model.get('elType') === 'global-widget') {
                addGeoSectionToPanel(panel, model);
            }
        });

        // Add geo-targeting to global colors
        elementor.hooks.addAction('panel/open_editor/global-colors', function (panel, model, view) {
            addGeoSectionToGlobalColors(panel, model);
        });

        // Add geo-targeting to global typography
        elementor.hooks.addAction('panel/open_editor/global-typography', function (panel, model, view) {
            addGeoSectionToGlobalTypography(panel, model);
        });
    }

    /**
     * Add geo section to panel
     */
    function addGeoSectionToPanel(panel, model) {
        var geoSection = $('<div class="elementor-panel-box elementor-panel-box-content">' +
            '<div class="elementor-panel-box-title">' + egpGlobalSettings.strings.geoTargeting + '</div>' +
            '<div class="elementor-panel-box-content">' +
            '<div class="elementor-control elementor-control-type-switcher">' +
            '<div class="elementor-control-input-wrapper">' +
            '<label class="elementor-switcher">' +
            '<input type="checkbox" id="egp_geo_enabled" class="elementor-switcher-input">' +
            '<span class="elementor-switcher-label elementor-switcher-label-right">' + egpGlobalSettings.strings.enableGeo + '</span>' +
            '</label>' +
            '</div>' +
            '</div>' +
            '<div class="elementor-control elementor-control-type-select2" id="egp_countries_wrapper" style="display: none;">' +
            '<label class="elementor-control-title">' + egpGlobalSettings.strings.targetCountries + '</label>' +
            '<div class="elementor-control-input-wrapper">' +
            '<select id="egp_target_countries" multiple class="elementor-select2">' +
            generateCountriesOptions() +
            '</select>' +
            '</div>' +
            '</div>' +
            '<div class="elementor-control elementor-control-type-select" id="egp_fallback_wrapper" style="display: none;">' +
            '<label class="elementor-control-title">' + egpGlobalSettings.strings.fallbackBehavior + '</label>' +
            '<div class="elementor-control-input-wrapper">' +
            '<select id="egp_fallback_behavior">' +
            '<option value="hide">Hide for non-matching countries</option>' +
            '<option value="show">Show for all countries</option>' +
            '<option value="default">Use default global value</option>' +
            '</select>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>');

        // Insert geo section after the main content section
        panel.$el.find('.elementor-panel-box-content').append(geoSection);

        // Initialize controls
        initializeGeoControls(panel, model);
    }

    /**
     * Add geo section to global colors
     */
    function addGeoSectionToGlobalColors(panel, model) {
        var geoSection = $('<div class="elementor-panel-box elementor-panel-box-content">' +
            '<div class="elementor-panel-box-title">' + egpGlobalSettings.strings.geoTargeting + '</div>' +
            '<div class="elementor-panel-box-content">' +
            '<div class="elementor-control elementor-control-type-switcher">' +
            '<div class="elementor-control-input-wrapper">' +
            '<label class="elementor-switcher">' +
            '<input type="checkbox" id="egp_geo_enabled_colors" class="elementor-switcher-input">' +
            '<span class="elementor-switcher-label elementor-switcher-label-right">' + egpGlobalSettings.strings.enableGeo + '</span>' +
            '</label>' +
            '</div>' +
            '</div>' +
            '<div class="elementor-control elementor-control-type-select2" id="egp_countries_wrapper_colors" style="display: none;">' +
            '<label class="elementor-control-title">' + egpGlobalSettings.strings.targetCountries + '</label>' +
            '<div class="elementor-control-input-wrapper">' +
            '<select id="egp_target_countries_colors" multiple class="elementor-select2">' +
            generateCountriesOptions() +
            '</select>' +
            '</div>' +
            '</div>' +
            '<div class="elementor-control elementor-control-type-color" id="egp_alternative_color_wrapper" style="display: none;">' +
            '<label class="elementor-control-title">Alternative Color</label>' +
            '<div class="elementor-control-input-wrapper">' +
            '<input type="text" id="egp_alternative_color" class="elementor-color-picker">' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>');

        panel.$el.find('.elementor-panel-box-content').append(geoSection);
        initializeGeoColorControls(panel, model);
    }

    /**
     * Add geo section to global typography
     */
    function addGeoSectionToGlobalTypography(panel, model) {
        var geoSection = $('<div class="elementor-panel-box elementor-panel-box-content">' +
            '<div class="elementor-panel-box-title">' + egpGlobalSettings.strings.geoTargeting + '</div>' +
            '<div class="elementor-panel-box-content">' +
            '<div class="elementor-control elementor-control-type-switcher">' +
            '<div class="elementor-control-input-wrapper">' +
            '<label class="elementor-switcher">' +
            '<input type="checkbox" id="egp_geo_enabled_typography" class="elementor-switcher-input">' +
            '<span class="elementor-switcher-label elementor-switcher-label-right">' + egpGlobalSettings.strings.enableGeo + '</span>' +
            '</label>' +
            '</div>' +
            '</div>' +
            '<div class="elementor-control elementor-control-type-select2" id="egp_countries_wrapper_typography" style="display: none;">' +
            '<label class="elementor-control-title">' + egpGlobalSettings.strings.targetCountries + '</label>' +
            '<div class="elementor-control-input-wrapper">' +
            '<select id="egp_target_countries_colors" multiple class="elementor-select2">' +
            generateCountriesOptions() +
            '</select>' +
            '</div>' +
            '</div>' +
            '<div class="elementor-control elementor-control-type-font" id="egp_alternative_font_wrapper" style="display: none;">' +
            '<label class="elementor-control-title">Alternative Font Family</label>' +
            '<div class="elementor-control-input-wrapper">' +
            '<select id="egp_alternative_font_family" class="elementor-control-font-family">' +
            generateFontOptions() +
            '</select>' +
            '</div>' +
            '</div>' +
            '<div class="elementor-control elementor-control-type-slider" id="egp_alternative_size_wrapper" style="display: none;">' +
            '<label class="elementor-control-title">Alternative Font Size</label>' +
            '<div class="elementor-control-input-wrapper">' +
            '<input type="range" id="egp_alternative_font_size" min="1" max="200" value="16">' +
            '<span class="elementor-control-value">16px</span>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>');

        panel.$el.find('.elementor-panel-box-content').append(geoSection);
        initializeGeoTypographyControls(panel, model);
    }

    /**
     * Initialize geo controls
     */
    function initializeGeoControls(panel, model) {
        var geoEnabled = $('#egp_geo_enabled');
        var countriesWrapper = $('#egp_countries_wrapper');
        var fallbackWrapper = $('#egp_fallback_wrapper');

        // Load existing settings
        loadExistingGeoSettings(model);

        // Handle geo enabled toggle
        geoEnabled.on('change', function () {
            var isEnabled = $(this).is(':checked');
            countriesWrapper.toggle(isEnabled);
            fallbackWrapper.toggle(isEnabled);

            if (isEnabled) {
                saveGeoSettings(model);
            }
        });

        // Handle countries change
        $('#egp_target_countries').on('change', function () {
            saveGeoSettings(model);
        });

        // Handle fallback change
        $('#egp_fallback_behavior').on('change', function () {
            saveGeoSettings(model);
        });
    }

    /**
     * Initialize geo color controls
     */
    function initializeGeoColorControls(panel, model) {
        var geoEnabled = $('#egp_geo_enabled_colors');
        var countriesWrapper = $('#egp_countries_wrapper_colors');
        var alternativeColorWrapper = $('#egp_alternative_color_wrapper');

        loadExistingGeoSettings(model);

        geoEnabled.on('change', function () {
            var isEnabled = $(this).is(':checked');
            countriesWrapper.toggle(isEnabled);
            alternativeColorWrapper.toggle(isEnabled);

            if (isEnabled) {
                saveGeoSettings(model);
            }
        });

        $('#egp_target_countries_colors').on('change', function () {
            saveGeoSettings(model);
        });

        $('#egp_alternative_color').on('change', function () {
            saveGeoSettings(model);
        });
    }

    /**
     * Initialize geo typography controls
     */
    function initializeGeoTypographyControls(panel, model) {
        var geoEnabled = $('#egp_geo_enabled_typography');
        var countriesWrapper = $('#egp_countries_wrapper_typography');
        var alternativeFontWrapper = $('#egp_alternative_font_wrapper');
        var alternativeSizeWrapper = $('#egp_alternative_size_wrapper');

        loadExistingGeoSettings(model);

        geoEnabled.on('change', function () {
            var isEnabled = $(this).is(':checked');
            countriesWrapper.toggle(isEnabled);
            alternativeFontWrapper.toggle(isEnabled);
            alternativeSizeWrapper.toggle(isEnabled);

            if (isEnabled) {
                saveGeoSettings(model);
            }
        });

        $('#egp_target_countries_colors').on('change', function () {
            saveGeoSettings(model);
        });

        $('#egp_alternative_font_family').on('change', function () {
            saveGeoSettings(model);
        });

        $('#egp_alternative_font_size').on('input', function () {
            var value = $(this).val();
            $(this).next('.elementor-control-value').text(value + 'px');
            saveGeoSettings(model);
        });
    }

    /**
     * Load existing geo settings
     */
    function loadExistingGeoSettings(model) {
        var settings = model.get('settings');

        if (settings.egp_geo_targeting_enabled === 'yes') {
            $('#egp_geo_enabled, #egp_geo_enabled_colors, #egp_geo_enabled_typography').prop('checked', true).trigger('change');

            if (settings.egp_target_countries) {
                $('#egp_target_countries, #egp_target_countries_colors').val(settings.egp_target_countries).trigger('change');
            }

            if (settings.egp_fallback_behavior) {
                $('#egp_fallback_behavior').val(settings.egp_fallback_behavior);
            }

            if (settings.egp_alternative_color) {
                $('#egp_alternative_color').val(settings.egp_alternative_color);
            }

            if (settings.egp_alternative_font_family) {
                $('#egp_alternative_font_family').val(settings.egp_alternative_font_family);
            }

            if (settings.egp_alternative_font_size) {
                $('#egp_alternative_font_size').val(settings.egp_alternative_font_size).trigger('input');
            }
        }
    }

    /**
     * Save geo settings
     */
    function saveGeoSettings(model) {
        var settings = model.get('settings') || {};

        // Get geo settings from form
        var geoEnabled = $('#egp_geo_enabled, #egp_geo_enabled_colors, #egp_geo_enabled_typography').is(':checked');
        var targetCountries = $('#egp_target_countries, #egp_target_countries_colors').val();
        var fallbackBehavior = $('#egp_fallback_behavior').val();
        var alternativeColor = $('#egp_alternative_color').val();
        var alternativeFontFamily = $('#egp_alternative_font_family').val();
        var alternativeFontSize = $('#egp_alternative_font_size').val();

        // Update settings
        settings.egp_geo_targeting_enabled = geoEnabled ? 'yes' : 'no';

        if (geoEnabled) {
            settings.egp_target_countries = targetCountries || [];
            settings.egp_fallback_behavior = fallbackBehavior || 'hide';
            settings.egp_alternative_color = alternativeColor || '';
            settings.egp_alternative_font_family = alternativeFontFamily || '';
            settings.egp_alternative_font_size = alternativeFontSize || '';
        } else {
            // Remove geo settings if disabled
            delete settings.egp_geo_targeting_enabled;
            delete settings.egp_target_countries;
            delete settings.egp_fallback_behavior;
            delete settings.egp_alternative_color;
            delete settings.egp_alternative_font_family;
            delete settings.egp_alternative_font_size;
        }

        // Update model
        model.set('settings', settings);

        // Trigger save
        elementor.channels.editor.trigger('change:popup:settings');
    }

    /**
     * Listen for global changes
     */
    function listenForGlobalChanges() {
        elementor.channels.editor.on('change:popup:settings', function () {
            // Handle global settings changes
            updateGlobalSettings();
        });
    }

    /**
     * Update global settings
     */
    function updateGlobalSettings() {
        // This function can be used to update global settings when needed
        // For now, we'll just log the change
        console.log('Global settings updated');
    }

    /**
     * Initialize existing global settings
     */
    function initializeExistingGlobalSettings() {
        // Load any existing global geo settings
        if (egpGlobalSettings && egpGlobalSettings.geo_targeting_enabled === 'yes') {
            // Apply global settings to new elements
            applyGlobalSettingsToNewElements();
        }
    }

    /**
     * Apply global settings to new elements
     */
    function applyGlobalSettingsToNewElements() {
        // This function can be used to apply global settings to new elements
        // For now, we'll just log the action
        console.log('Applying global settings to new elements');
    }

    /**
     * Generate countries options
     */
    function generateCountriesOptions() {
        var options = '';
        if (egpGlobalSettings && egpGlobalSettings.countries) {
            $.each(egpGlobalSettings.countries, function (code, name) {
                options += '<option value="' + code + '">' + name + '</option>';
            });
        }
        return options;
    }

    /**
     * Generate font options
     */
    function generateFontOptions() {
        var fonts = [
            'Arial, sans-serif',
            'Helvetica, sans-serif',
            'Times New Roman, serif',
            'Georgia, serif',
            'Verdana, sans-serif',
            'Tahoma, sans-serif',
            'Trebuchet MS, sans-serif',
            'Impact, sans-serif',
            'Comic Sans MS, cursive',
            'Courier New, monospace'
        ];

        var options = '<option value="">Default</option>';
        $.each(fonts, function (index, font) {
            options += '<option value="' + font + '">' + font + '</option>';
        });
        return options;
    }

})(jQuery);
