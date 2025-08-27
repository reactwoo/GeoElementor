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
                                <option value="NL">Netherlands</option>
                                <option value="SE">Sweden</option>
                                <option value="NO">Norway</option>
                                <option value="DK">Denmark</option>
                                <option value="FI">Finland</option>
                                <option value="IT">Italy</option>
                                <option value="ES">Spain</option>
                                <option value="PT">Portugal</option>
                                <option value="IE">Ireland</option>
                                <option value="BE">Belgium</option>
                                <option value="CH">Switzerland</option>
                                <option value="AT">Austria</option>
                                <option value="PL">Poland</option>
                                <option value="CZ">Czech Republic</option>
                                <option value="HU">Hungary</option>
                                <option value="RO">Romania</option>
                                <option value="BG">Bulgaria</option>
                                <option value="HR">Croatia</option>
                                <option value="SI">Slovenia</option>
                                <option value="SK">Slovakia</option>
                                <option value="LT">Lithuania</option>
                                <option value="LV">Latvia</option>
                                <option value="EE">Estonia</option>
                                <option value="MT">Malta</option>
                                <option value="CY">Cyprus</option>
                                <option value="LU">Luxembourg</option>
                                <option value="GR">Greece</option>
                                <option value="RU">Russia</option>
                                <option value="UA">Ukraine</option>
                                <option value="BY">Belarus</option>
                                <option value="MD">Moldova</option>
                                <option value="GE">Georgia</option>
                                <option value="AM">Armenia</option>
                                <option value="AZ">Azerbaijan</option>
                                <option value="TR">Turkey</option>
                                <option value="IL">Israel</option>
                                <option value="LB">Lebanon</option>
                                <option value="JO">Jordan</option>
                                <option value="SY">Syria</option>
                                <option value="IQ">Iraq</option>
                                <option value="IR">Iran</option>
                                <option value="SA">Saudi Arabia</option>
                                <option value="AE">United Arab Emirates</option>
                                <option value="QA">Qatar</option>
                                <option value="KW">Kuwait</option>
                                <option value="BH">Bahrain</option>
                                <option value="OM">Oman</option>
                                <option value="YE">Yemen</option>
                                <option value="EG">Egypt</option>
                                <option value="LY">Libya</option>
                                <option value="TN">Tunisia</option>
                                <option value="DZ">Algeria</option>
                                <option value="MA">Morocco</option>
                                <option value="SD">Sudan</option>
                                <option value="ET">Ethiopia</option>
                                <option value="KE">Kenya</option>
                                <option value="NG">Nigeria</option>
                                <option value="ZA">South Africa</option>
                                <option value="GH">Ghana</option>
                                <option value="CI">Ivory Coast</option>
                                <option value="SN">Senegal</option>
                                <option value="ML">Mali</option>
                                <option value="BF">Burkina Faso</option>
                                <option value="NE">Niger</option>
                                <option value="TD">Chad</option>
                                <option value="CM">Cameroon</option>
                                <option value="CF">Central African Republic</option>
                                <option value="CG">Republic of the Congo</option>
                                <option value="CD">Democratic Republic of the Congo</option>
                                <option value="GA">Gabon</option>
                                <option value="GQ">Equatorial Guinea</option>
                                <option value="ST">São Tomé and Príncipe</option>
                                <option value="AO">Angola</option>
                                <option value="NA">Namibia</option>
                                <option value="BW">Botswana</option>
                                <option value="ZW">Zimbabwe</option>
                                <option value="ZM">Zambia</option>
                                <option value="MW">Malawi</option>
                                <option value="MZ">Mozambique</option>
                                <option value="MG">Madagascar</option>
                                <option value="MU">Mauritius</option>
                                <option value="SC">Seychelles</option>
                                <option value="KM">Comoros</option>
                                <option value="DJ">Djibouti</option>
                                <option value="SO">Somalia</option>
                                <option value="ER">Eritrea</option>
                                <option value="SS">South Sudan</option>
                                <option value="RW">Rwanda</option>
                                <option value="BI">Burundi</option>
                                <option value="TZ">Tanzania</option>
                                <option value="UG">Uganda</option>
                                <option value="SS">South Sudan</option>
                                <option value="CF">Central African Republic</option>
                                <option value="TD">Chad</option>
                                <option value="NE">Niger</option>
                                <option value="ML">Mali</option>
                                <option value="BF">Burkina Faso</option>
                                <option value="SN">Senegal</option>
                                <option value="CI">Ivory Coast</option>
                                <option value="GH">Ghana</option>
                                <option value="NG">Nigeria</option>
                                <option value="KE">Kenya</option>
                                <option value="ET">Ethiopia</option>
                                <option value="SD">Sudan</option>
                                <option value="MA">Morocco</option>
                                <option value="DZ">Algeria</option>
                                <option value="TN">Tunisia</option>
                                <option value="LY">Libya</option>
                                <option value="EG">Egypt</option>
                                <option value="YE">Yemen</option>
                                <option value="OM">Oman</option>
                                <option value="BH">Bahrain</option>
                                <option value="KW">Kuwait</option>
                                <option value="QA">Qatar</option>
                                <option value="AE">United Arab Emirates</option>
                                <option value="SA">Saudi Arabia</option>
                                <option value="IR">Iran</option>
                                <option value="IQ">Iraq</option>
                                <option value="SY">Syria</option>
                                <option value="JO">Jordan</option>
                                <option value="LB">Lebanon</option>
                                <option value="IL">Israel</option>
                                <option value="TR">Turkey</option>
                                <option value="AZ">Azerbaijan</option>
                                <option value="AM">Armenia</option>
                                <option value="GE">Georgia</option>
                                <option value="MD">Moldova</option>
                                <option value="BY">Belarus</option>
                                <option value="UA">Ukraine</option>
                                <option value="RU">Russia</option>
                                <option value="GR">Greece</option>
                                <option value="LU">Luxembourg</option>
                                <option value="CY">Cyprus</option>
                                <option value="MT">Malta</option>
                                <option value="EE">Estonia</option>
                                <option value="LV">Latvia</option>
                                <option value="LT">Lithuania</option>
                                <option value="SK">Slovakia</option>
                                <option value="SI">Slovenia</option>
                                <option value="HR">Croatia</option>
                                <option value="BG">Bulgaria</option>
                                <option value="RO">Romania</option>
                                <option value="HU">Hungary</option>
                                <option value="CZ">Czech Republic</option>
                                <option value="PL">Poland</option>
                                <option value="AT">Austria</option>
                                <option value="CH">Switzerland</option>
                                <option value="BE">Belgium</option>
                                <option value="IE">Ireland</option>
                                <option value="PT">Portugal</option>
                                <option value="ES">Spain</option>
                                <option value="IT">Italy</option>
                                <option value="FI">Finland</option>
                                <option value="DK">Denmark</option>
                                <option value="NO">Norway</option>
                                <option value="SE">Sweden</option>
                                <option value="NL">Netherlands</option>
                                <option value="CN">China</option>
                                <option value="IN">India</option>
                                <option value="BR">Brazil</option>
                                <option value="JP">Japan</option>
                                <option value="AU">Australia</option>
                                <option value="FR">France</option>
                                <option value="GB">United Kingdom</option>
                                <option value="DE">Germany</option>
                                <option value="CA">Canada</option>
                                <option value="US">United States</option>
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

            // Initialize native country select (no Select2)
            this.initializeCountrySelect(panel);
        },

        /**
         * Initialize country selection without Select2
         */
        initializeCountrySelect: function (panel) {
            var $countriesSelect = panel.$el.find('#egp_countries');
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
