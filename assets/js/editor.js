/**
 * Elementor Editor Integration for Geo Targeting
 * Adds geo targeting controls to the Advanced tab
 */

(function ($) {
    'use strict';

    var GeoElementorEditor = {
        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            // Wait for Elementor to be ready
            if (typeof elementor !== 'undefined') {
                this.addGeoControls();
            } else {
                $(document).on('elementor/init', this.addGeoControls.bind(this));
            }
        },

        addGeoControls: function () {
            // Add geo targeting section to widgets
            elementor.hooks.addAction('panel/open_editor/widget', this.addGeoPanel);

            // Add geo targeting section to popups
            elementor.hooks.addAction('panel/open_editor/popup', this.addGeoPanel);
        },

        addGeoPanel: function (panel, model) {
            var $geoSection = $('<div class="elementor-panel-option">');
            $geoSection.html(`
                <div class="elementor-panel-heading-title elementor-panel-heading-title">
                    <i class="eicon-location-alt"></i> Geo Targeting
                </div>
                <div class="elementor-panel-field">
                    <label class="elementor-panel-field-label">Enable Geo Targeting</label>
                    <div class="elementor-panel-field-control">
                        <label class="elementor-switch">
                            <input type="checkbox" id="egp_enable_geo" class="egp-geo-toggle">
                            <span class="elementor-switch-label"></span>
                        </label>
                    </div>
                </div>
                <div id="egp_geo_options" style="display: none;">
                    <div class="elementor-panel-field">
                        <label class="elementor-panel-field-label">Target Countries</label>
                        <div class="elementor-panel-field-control">
                            <select id="egp_countries" multiple="multiple" style="width: 100%; min-height: 80px;">
                                <option value="US">United States</option>
                                <option value="CA">Canada</option>
                                <option value="GB">United Kingdom</option>
                                <option value="DE">Germany</option>
                                <option value="FR">France</option>
                                <option value="AU">Australia</option>
                                <option value="JP">Japan</option>
                                <option value="BR">Brazil</option>
                                <option value="IN">India</option>
                                <option value="CN">China</option>
                            </select>
                            <p class="description">Hold Ctrl/Cmd to select multiple countries</p>
                        </div>
                    </div>
                    <div class="elementor-panel-field">
                        <label class="elementor-panel-field-label">Priority</label>
                        <div class="elementor-panel-field-control">
                            <input type="number" id="egp_priority" min="1" max="100" value="50" style="width: 100%;">
                            <p class="description">Higher numbers take precedence (1-100)</p>
                        </div>
                    </div>
                    <div class="elementor-panel-field">
                        <label class="elementor-panel-field-label">Tracking ID</label>
                        <div class="elementor-panel-field-control">
                            <input type="text" id="egp_tracking_id" style="width: 100%;" placeholder="auto-generated">
                            <p class="description">Used for analytics and tracking</p>
                        </div>
                    </div>
                </div>
            `);

            // Insert into appropriate section based on element type
            var $targetSection;
            if (model.get('elType') === 'popup') {
                // For popups, add to popup layout section
                $targetSection = panel.$el.find('.elementor-panel-section-popup_layout');
            } else {
                // For widgets, add to Advanced tab
                $targetSection = panel.$el.find('.elementor-panel-tab-content[data-tab="advanced"]');
            }

            if ($targetSection.length) {
                $targetSection.append($geoSection);
            }

            // Bind events
            this.bindGeoEvents(panel, model);
        },

        bindGeoEvents: function (panel, model) {
            var $geoToggle = panel.$el.find('#egp_enable_geo');
            var $geoOptions = panel.$el.find('#egp_geo_options');

            // Toggle geo options visibility
            $geoToggle.on('change', function () {
                if (this.checked) {
                    $geoOptions.show();
                    // Load existing geo settings if any
                    GeoElementorEditor.loadExistingSettings(panel, model);
                } else {
                    $geoOptions.hide();
                }
            });

            // Save geo settings when element is saved
            panel.$el.on('change', 'input, select', function () {
                GeoElementorEditor.saveGeoSettings(panel, model);
            });
        },

        loadExistingSettings: function (panel, model) {
            // Load existing geo settings from the model
            var settings = model.get('settings');
            if (settings && settings.egp_geo_enabled) {
                panel.$el.find('#egp_countries').val(settings.egp_countries || []);
                panel.$el.find('#egp_priority').val(settings.egp_priority || 50);
                panel.$el.find('#egp_tracking_id').val(settings.egp_tracking_id || '');
            }
        },

        saveGeoSettings: function (panel, model) {
            var settings = model.get('settings') || {};

            settings.egp_geo_enabled = panel.$el.find('#egp_enable_geo').is(':checked');
            settings.egp_countries = panel.$el.find('#egp_countries').val() || [];
            settings.egp_priority = parseInt(panel.$el.find('#egp_priority').val()) || 50;
            settings.egp_tracking_id = panel.$el.find('#egp_tracking_id').val() || '';

            // Auto-generate tracking ID if empty
            if (!settings.egp_tracking_id && settings.egp_geo_enabled) {
                settings.egp_tracking_id = 'geo_' + model.get('id') + '_' + Date.now();
                panel.$el.find('#egp_tracking_id').val(settings.egp_tracking_id);
            }

            model.set('settings', settings);

            // Save geo settings to database for central administration
            if (settings.egp_geo_enabled) {
                this.saveGeoRuleToDatabase(model, settings);
            } else {
                // Remove geo rule if disabled
                this.removeGeoRuleFromDatabase(model);
            }
        },

        saveGeoRuleToDatabase: function (model, settings) {
            var ruleData = {
                element_id: model.get('id'),
                element_type: model.get('elType') || 'widget',
                element_title: model.get('settings').get('_title') || 'Untitled Element',
                target_type: 'elementor',
                target_id: model.get('id'),
                countries: settings.egp_countries,
                priority: settings.egp_priority,
                tracking_id: settings.egp_tracking_id,
                active: true,
                source: 'elementor'
            };

            // Send to backend to save
            $.post(ajaxurl, {
                action: 'egp_save_elementor_geo_rule',
                rule_data: ruleData,
                nonce: egpEditor.nonce
            }, function (response) {
                if (response.success) {
                    console.log('Geo rule saved for element:', model.get('id'));
                } else {
                    console.error('Failed to save geo rule:', response.data);
                }
            });
        },

        removeGeoRuleFromDatabase: function (model) {
            $.post(ajaxurl, {
                action: 'egp_remove_elementor_geo_rule',
                element_id: model.get('id'),
                nonce: egpEditor.nonce
            }, function (response) {
                if (response.success) {
                    console.log('Geo rule removed for element:', model.get('id'));
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function () {
        GeoElementorEditor.init();
    });

})(jQuery);
