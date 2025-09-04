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
            // Add geo targeting section to widgets
            elementor.hooks.addAction('panel/open_editor/widget', this.addGeoPanel);
            // Also attach to sections/containers/columns (Elementor 3+ containers)
            elementor.hooks.addAction('panel/open_editor/section', this.addGeoPanel);
            elementor.hooks.addAction('panel/open_editor/container', this.addGeoPanel);
            elementor.hooks.addAction('panel/open_editor/column', this.addGeoPanel);
            // Add geo targeting section to popups
            elementor.hooks.addAction('panel/open_editor/popup', this.addGeoPanel);
        },

        addGeoPanel: function (panel, model) {
            // Avoid duplicates if panel re-opens
            try { panel.$el.find('#egp_geo_panel_root').remove(); } catch (e) { }
            var $geoSection = $('<div class="elementor-panel-option" id="egp_geo_panel_root">');
            $geoSection.html(`
                <div class="elementor-panel-heading-title elementor-panel-heading-title">
                    <i class="eicon-location-alt"></i> Geo Targeting
                </div>
                <div class="elementor-panel-field" id="egp_element_meta_row">
                    <label class="elementor-panel-field-label">Element ID</label>
                    <div class="elementor-panel-field-control">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <code id="egp_element_id_code" style="padding:2px 6px;border:1px solid #e2e8f0;border-radius:3px;background:#f8fafc;">—</code>
                            <button type="button" class="elementor-button elementor-button-default" id="egp_copy_element_id" style="padding:2px 6px;line-height:1.6;">Copy</button>
                        </div>
                        <p class="description">Use this value in Rules or Groups when targeting by Elementor element ID.</p>
                    </div>
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
                            <select id="egp_countries" multiple="multiple" style="width: 100%; min-height: 80px;"></select>
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
            var elType = model && model.get ? (model.get('elType') || '') : '';
            var tryAppend = function (retries) {
                retries = retries || 0;
                var $dest;
                if (elType === 'popup') {
                    $dest = panel.$el.find('.elementor-panel-section-popup_layout');
                } else {
                    $dest = panel.$el.find('.elementor-panel-tab-content[data-tab="advanced"]');
                    if (!$dest.length) { $dest = panel.$el.find('.elementor-panel-tabs-content .elementor-panel-tab-content[data-tab="advanced"]'); }
                    if (!$dest.length) { $dest = panel.$el.find('.elementor-panel-tabs-content'); }
                }
                if ($dest && $dest.length) {
                    $dest.append($geoSection);
                    return true;
                }
                if (retries < 20) { setTimeout(function () { tryAppend(retries + 1); }, 100); }
                return false;
            };
            tryAppend(0);
            try { if (!$targetSection.length && window.console && console.warn) { console.warn('[EGP] Advanced tab not found for', elType); } } catch (e) { }

            // Bind events (stable reference)
            GeoElementorEditor.bindGeoEvents(panel, model);

            // Initialize native country select (no Select2)
            GeoElementorEditor.initializeCountrySelect(panel);

            // Populate with existing rule by popup post ID, fallback to element ID
            try {
                var popupPostId = (elementor && elementor.config && elementor.config.document && elementor.config.document.id) ? elementor.config.document.id : '';
                var elementId = model && model.get ? model.get('id') : '';
                // Populate element id meta row
                try {
                    panel.$el.find('#egp_element_id_code').text(elementId || '—');
                    panel.$el.find('#egp_copy_element_id').off('click.egp').on('click.egp', function () {
                        try { navigator.clipboard.writeText(String(elementId || '')); } catch (e) { }
                    });
                } catch (e) { }
                var paramsByPopup = { action: 'egp_get_rule_by_popup', popup_id: popupPostId, nonce: (window.egpEditor && egpEditor.nonce) || '' };
                var paramsByElement = { action: 'egp_get_rule_by_element', element_id: elementId, nonce: (window.egpEditor && egpEditor.nonce) || '' };

                var applyRule = function (data) {
                    panel.$el.find('#egp_enable_geo').prop('checked', true);
                    panel.$el.find('#egp_geo_options').show();
                    if (Array.isArray(data.countries)) {
                        panel.$el.find('#egp_countries').val(data.countries);
                    }
                    var summary = $('<div class=\"elementor-panel-field\"><p class=\"description\">Rule: ' + data.title + ' (Priority ' + (data.priority || 0) + ')</p></div>');
                    $geoSection.append(summary);
                    var ruleLinks = $('<div class=\"elementor-panel-field\"><a class=\"button button-small\" target=\"_blank\" href=\"' + (window.ajaxurl ? window.ajaxurl.replace('admin-ajax.php', 'post.php?post=' + data.id + '&action=edit') : '#') + '\">Edit Rule</a></div>');
                    $geoSection.append(ruleLinks);
                };

                if (popupPostId) {
                    $.post(egpEditor.ajaxUrl, paramsByPopup).done(function (resp) {
                        if (resp && resp.success && resp.data) {
                            applyRule(resp.data);
                        } else if (elementId) {
                            $.post(egpEditor.ajaxUrl, paramsByElement).done(function (resp2) {
                                if (resp2 && resp2.success && resp2.data) {
                                    applyRule(resp2.data);
                                }
                            });
                        }
                    });
                } else if (elementId) {
                    $.post(egpEditor.ajaxUrl, paramsByElement).done(function (resp3) {
                        if (resp3 && resp3.success && resp3.data) {
                            applyRule(resp3.data);
                        }
                    });
                }
            } catch (e) { }
        },

        /**
         * Initialize country selection without Select2
         */
        initializeCountrySelect: function (panel) {
            var $countriesSelect = panel.$el.find('#egp_countries');
            // Fetch countries from backend to ensure complete list (incl. Singapore)
            try {
                $.post(egpEditor.ajaxUrl, { action: 'egp_get_countries', nonce: egpEditor.nonce }).done(function (resp) {
                    if (resp && resp.success && resp.data) {
                        var optionsHtml = '';
                        Object.keys(resp.data).sort().forEach(function (code) {
                            var name = resp.data[code];
                            optionsHtml += '<option value="' + code + '">' + name + '</option>';
                        });
                        $countriesSelect.html(optionsHtml);
                    }
                });
            } catch (e) { }
            // Style native select for usability
            $countriesSelect.css({
                'min-height': '120px',
                'padding': '8px',
                'border': '1px solid #ddd',
                'border-radius': '4px',
                'background-color': '#fff'
            });
            $countriesSelect.after('<p class="description" style="margin-top: 5px; color: #666;">Hold Ctrl/Cmd to select multiple countries.</p>');
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
            var docType = (elementor && elementor.config && elementor.config.document && elementor.config.document.type) ? elementor.config.document.type : '';
            var popupPostId = (elementor && elementor.config && elementor.config.document && elementor.config.document.id) ? elementor.config.document.id : '';
            var elType = model && model.get ? (model.get('elType') || '') : '';
            var isPopupDoc = (docType === 'popup' || elType === 'popup');
            var targetType = isPopupDoc ? 'popup' : 'elementor';
            var targetId = isPopupDoc && popupPostId ? String(popupPostId) : String(model.get('id'));

            var ruleData = {
                target_type: targetType,
                target_id: targetId,
                countries: settings.egp_countries,
                priority: settings.egp_priority,
                active: true,
                source: 'elementor',
                title: (model.get('settings') && model.get('settings').get('_title')) || 'Elementor Geo Rule',
                element_type: elType || 'widget',
                tracking_id: settings.egp_tracking_id
            };

            // Send to backend to save using unified endpoint
            $.post(egpEditor.ajaxUrl, {
                action: 'egp_save_elementor_geo_rule',
                target_type: targetType,
                target_id: targetId,
                countries: settings.egp_countries,
                priority: settings.egp_priority,
                active: true,
                source: 'elementor',
                title: ruleData.title,
                element_type: ruleData.element_type,
                tracking_id: settings.egp_tracking_id,
                nonce: egpEditor.nonce
            }, function (response) {
                if (response.success) {
                    console.log('Geo rule saved successfully:', response.data);
                } else {
                    console.error('Failed to save geo rule:', response.data);
                }
            });
        },

        removeGeoRuleFromDatabase: function (model) {
            $.post(egpEditor.ajaxUrl, {
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
