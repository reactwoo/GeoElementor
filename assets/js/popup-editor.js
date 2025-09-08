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
                            var previewView = (elementor.getPreviewView && typeof elementor.getPreviewView === 'function') ? elementor.getPreviewView() : null;
                            if (previewView && typeof previewView.isEditMode === 'function') {
                                isEditor = !!previewView.isEditMode();
                            } else if (typeof elementor.isEditMode === 'function') {
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
                                // Do NOT initialize fallback in editor preview context; silently skip
                                try {
                                    var inPreview = (window.location && window.location.search && window.location.search.indexOf('elementor-preview=') !== -1);
                                    if (!inPreview) {
                                        self.setupFrontend();
                                    }
                                } catch (e) { /* ignore */ }
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
            // Do not initialize any frontend system inside Elementor editor/preview contexts
            try {
                if (typeof elementor !== 'undefined') {
                    return;
                }
                if (window.location && /[?&]elementor-preview=/.test(window.location.search)) {
                    return;
                }
            } catch (e) { /* ignore */ }

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
                            <select id="egp_countries" multiple="multiple" style="width: 100%; min-height: 120px;"></select>
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

            // Populate countries list from server, with JSON fallback
            (function () {
                var fill = function (map) {
                    try {
                        var $sel = panel.$el.find('#egp_countries');
                        if (!$sel.length) { return; }
                        $sel.empty();
                        var codes = Object.keys(map || {});
                        codes.sort(function (a, b) { return String(map[a]).localeCompare(String(map[b])); });
                        codes.forEach(function (code) { $sel.append('<option value="' + code + '">' + map[code] + '</option>'); });
                    } catch (e) { }
                };
                var tryJson = function () {
                    var base = (window.egpPopupEditor && egpPopupEditor.assetsUrl) ? egpPopupEditor.assetsUrl : ((window.egpEditor && egpEditor.assetsUrl) ? egpEditor.assetsUrl : '');
                    var url = base ? (base + 'data/countries.json') : '';
                    if (!url && window.location && window.location.origin) {
                        url = window.location.origin + '/wp-content/plugins/geo-elementor/assets/data/countries.json';
                    }
                    if (!url) return;
                    $.getJSON(url).done(function (arr) {
                        if (Array.isArray(arr)) {
                            var map = {}; arr.forEach(function (it) { if (it && it.code && it.name) { map[it.code] = it.name; } });
                            fill(map);
                        }
                    });
                };
                try {
                    var params = { action: 'egp_get_countries', nonce: (window.egpPopupEditor && egpPopupEditor.nonce) || (window.egpEditor && egpEditor.nonce) || '' };
                    var aj = (window.egpPopupEditor && egpPopupEditor.ajaxUrl) || (window.egpEditor && egpEditor.ajaxUrl) || window.ajaxurl;
                    if (!aj || !params.nonce) { throw new Error('missing'); }
                    $.post(aj, params).done(function (resp) {
                        if (resp && resp.success && resp.data) { fill(resp.data); }
                        else { tryJson(); }
                    }).fail(tryJson);
                } catch (e) {
                    tryJson();
                }
            })();

            // Bind events
            EGP_Popup_Editor_JS.bindGeoEvents(panel, model);

            // Populate with existing rule (if any)
            try {
                var elementId = model && model.get ? model.get('id') : '';
                var popupPostId = (model && model.get && model.get('settings') && model.get('settings').get) ? (model.get('settings').get('post_id') || '') : '';
                var paramsByElement = { action: 'egp_get_rule_by_element', element_id: elementId, nonce: (window.egpEditor && egpEditor.nonce) || '' };
                var paramsByPopup = { action: 'egp_get_rule_by_popup', popup_id: popupPostId, nonce: (window.egpEditor && egpEditor.nonce) || '' };

                var applyRule = function (data) {
                    panel.$el.find('#egp_enable_geo').prop('checked', true);
                    panel.$el.find('#egp_geo_options').show();
                    if (Array.isArray(data.countries)) {
                        panel.$el.find('#egp_countries').val(data.countries);
                    }
                    var summary = $('<div class="elementor-panel-field"><p class="description">Rule: ' + data.title + ' (Priority ' + (data.priority || 0) + ')</p></div>');
                    $geoSection.append(summary);
                    var ruleLinks = $('<div class="elementor-panel-field"><a class="button button-small" target="_blank" href="' + (window.ajaxurl ? window.ajaxurl.replace('admin-ajax.php', 'post.php?post=' + data.id + '&action=edit') : '#') + '">Edit Rule</a></div>');
                    $geoSection.append(ruleLinks);
                };

                $.get(ajaxurl, paramsByElement).done(function (resp) {
                    if (resp && resp.success && resp.data) {
                        applyRule(resp.data);
                    } else if (popupPostId) {
                        $.get(ajaxurl, paramsByPopup).done(function (resp2) {
                            if (resp2 && resp2.success && resp2.data) {
                                applyRule(resp2.data);
                            }
                        });
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
