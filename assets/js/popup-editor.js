/**
 * Popup Editor JavaScript - Clean Implementation
 * No more infinite loops!
 */

(function ($) {
    'use strict';

    var EGP_Popup_Editor_JS = {
        isInitialized: false,

        init: function () {
            if (this.isInitialized) {
                return;
            }

            console.log('[EGP] Popup Editor initializing...');

            // Wait for DOM and Elementor events
            var self = this;
            var boot = function () { self.setupOnce(); };
            $(document).ready(boot);
            $(window).on('elementor/init elementor/editor/init', boot);
        },

        setupOnce: function () {
            if (this.isInitialized) {
                return;
            }

            try {
                var self = this;
                var tryEditor = function () {
                    if (typeof elementor !== 'undefined') {
                        try {
                            var isEditor = false;
                            if (elementor.getPreviewView && elementor.getPreviewView().isEditMode) {
                                isEditor = !!elementor.getPreviewView().isEditMode();
                            } else if (elementor.isEditMode && typeof elementor.isEditMode === 'function') {
                                isEditor = !!elementor.isEditMode();
                            }
                            if (isEditor) {
                                self.setupElementorEditor();
                                self.isInitialized = true;
                                console.log('[EGP] Popup Editor initialized successfully (editor)');
                                return true;
                            }
                        } catch (e) {
                            // swallow and retry as frontend
                        }
                    }
                    return false;
                };

                // Retry a few times before assuming frontend
                if (!tryEditor()) {
                    var attempts = 0;
                    var maxAttempts = 10;
                    var timer = setInterval(function () {
                        attempts += 1;
                        if (tryEditor() || attempts >= maxAttempts) {
                            clearInterval(timer);
                            if (!self.isInitialized) {
                                self.setupFrontend();
                                self.isInitialized = true;
                                console.log('[EGP] Popup Editor initialized successfully (frontend)');
                            }
                        }
                    }, 200);
                }

            } catch (error) {
                console.error('[EGP] Error initializing popup editor:', error);
            }
        },

        setupElementorEditor: function () {
            console.log('[EGP] Setting up Elementor editor integration');

            // Add geo targeting controls to popup editor
            if (typeof elementor.hooks !== 'undefined') {
                elementor.hooks.addAction('panel/open_editor/popup', this.addGeoControls);
            }
        },

        setupFrontend: function () {
            console.log('[EGP] Setting up frontend popup system');

            // Check if Elementor Pro popups are available
            if (typeof elementorProFrontend !== 'undefined' && elementorProFrontend.modules.popup) {
                console.log('[EGP] Elementor Pro popups detected');
                this.setupElementorProPopups();
            } else {
                console.log('[EGP] Using fallback popup system');
                this.setupFallbackPopups();
            }
        },

        setupElementorProPopups: function () {
            // Hook into Elementor Pro popup events
            $(document).on('elementor/popup/show', function (event, popupId) {
                EGP_Popup_Editor_JS.trackPopupView(popupId);
            });

            $(document).on('elementor/popup/hide', function (event, popupId) {
                EGP_Popup_Editor_JS.trackPopupClose(popupId);
            });
        },

        setupFallbackPopups: function () {
            // Only set up if fallback popups are enabled
            if (!this.shouldUseFallbackPopups()) {
                return;
            }

            // Bind popup events
            $(document).on('click', '.egp-popup-close', function (e) {
                e.preventDefault();
                EGP_Popup_Editor_JS.hidePopup($(this).closest('.egp-popup'));
            });

            $(document).on('click', '.egp-popup-overlay', function (e) {
                if (e.target === this) {
                    EGP_Popup_Editor_JS.hidePopup($(this).find('.egp-popup'));
                }
            });

            $(document).on('keydown', function (e) {
                if (e.keyCode === 27) { // ESC key
                    EGP_Popup_Editor_JS.hideAllPopups();
                }
            });
        },

        shouldUseFallbackPopups: function () {
            // JS cannot call PHP get_option; rely on localized setting if provided
            return !!(window.egpSettings && window.egpSettings.useFallbackPopups);
        },

        addGeoControls: function (panel, model) {
            console.log('[EGP] Adding geo controls to popup editor');

            // Add geo targeting section
            var $geoSection = $('<div class="elementor-panel-option">');
            $geoSection.html(`
                <div class="elementor-panel-heading-title">
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
                </div>
            `);

            // Insert into popup editor
            var $popupLayoutSection = panel.$el.find('.elementor-panel-section-popup_layout');
            if ($popupLayoutSection.length) {
                $popupLayoutSection.append($geoSection);
            }

            // Bind events
            EGP_Popup_Editor_JS.bindGeoEvents(panel, model);

            // Populate with existing rule (if any)
            try {
                $.get(ajaxurl, {
                    action: 'egp_get_rule_by_element',
                    element_id: model.get('id'),
                    nonce: (window.egpEditor && egpEditor.nonce) || ''
                }).done(function (resp) {
                    if (resp && resp.success && resp.data) {
                        // Auto-enable and fill countries
                        panel.$el.find('#egp_enable_geo').prop('checked', true);
                        panel.$el.find('#egp_geo_options').show();
                        if (Array.isArray(resp.data.countries)) {
                            panel.$el.find('#egp_countries').val(resp.data.countries);
                        }
                        // Add rule summary
                        var summary = $('<div class="elementor-panel-field"><p class="description">Rule: ' + resp.data.title + ' (Priority ' + (resp.data.priority || 0) + ')</p></div>');
                        $geoSection.append(summary);
                        // Quick links
                        var ruleLinks = $('<div class="elementor-panel-field"><a class="button button-small" target="_blank" href="' + (window.ajaxurl ? window.ajaxurl.replace('admin-ajax.php', 'post.php?post=' + resp.data.id + '&action=edit') : '#') + '">Edit Rule</a></div>');
                        $geoSection.append(ruleLinks);
                    }
                });
            } catch (e) { }
        },

        bindGeoEvents: function (panel, model) {
            var $geoToggle = panel.$el.find('#egp_enable_geo');
            var $geoOptions = panel.$el.find('#egp_geo_options');

            // Toggle geo options visibility
            $geoToggle.on('change', function () {
                if (this.checked) {
                    $geoOptions.show();
                } else {
                    $geoOptions.hide();
                }
            });

            // Save geo settings when popup is saved
            panel.$el.on('change', 'input, select', function () {
                EGP_Popup_Editor_JS.saveGeoSettings(panel, model);
            });
        },

        saveGeoSettings: function (panel, model) {
            var settings = model.get('settings') || {};

            settings.egp_geo_enabled = panel.$el.find('#egp_enable_geo').is(':checked');
            settings.egp_countries = panel.$el.find('#egp_countries').val() || [];

            model.set('settings', settings);
            console.log('[EGP] Geo settings saved for popup:', model.get('id'));
        },

        hidePopup: function ($popup) {
            if (!$popup || $popup.length === 0) {
                return;
            }

            $popup.removeClass('egp-popup-active');
            $('body').removeClass('egp-popup-open');

            console.log('[EGP] Popup hidden:', $popup.attr('id'));
        },

        hideAllPopups: function () {
            $('.egp-popup').removeClass('egp-popup-active');
            $('body').removeClass('egp-popup-open');
            console.log('[EGP] All popups hidden');
        },

        trackPopupView: function (popupId) {
            console.log('[EGP] Popup viewed:', popupId);

            // Send tracking data if available
            if (typeof gtag !== 'undefined') {
                gtag('event', 'popup_viewed', {
                    'popup_id': popupId,
                    'event_category': 'engagement'
                });
            }

            // Also track in data layer
            if (window.dataLayer) {
                window.dataLayer.push({
                    'event': 'popup_viewed',
                    'popup_id': popupId
                });
            }
        },

        trackPopupClose: function (popupId) {
            console.log('[EGP] Popup closed:', popupId);

            // Send tracking data if available
            if (typeof gtag !== 'undefined') {
                gtag('event', 'popup_closed', {
                    'popup_id': popupId,
                    'event_category': 'engagement'
                });
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function () {
        EGP_Popup_Editor_JS.init();
    });

})(jQuery);
