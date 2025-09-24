/**
 * Enhanced Elementor Editor Integration
 * Fixes country selection persistence and rule management
 */

(function ($) {
    'use strict';

    var EGPEditorEnhanced = {
        init: function () {
            this.bindEvents();
            console.log('[EGP Enhanced] Editor integration loaded');
        },

        bindEvents: function () {
            var self = this;
            
            // Wait for Elementor to be ready
            function ready() {
                if (typeof elementor !== 'undefined' && elementor.hooks && elementor.channels && elementor.channels.editor) {
                    self.setupElementorIntegration();
                    return;
                }
                setTimeout(ready, 100);
            }
            ready();
        },

        setupElementorIntegration: function () {
            var self = this;
            
            // Listen for panel changes
            if (elementor.channels && elementor.channels.editor) {
                elementor.channels.editor.on('section:activated', function (sectionName, editor) {
                    if (sectionName === 'egp_geo_enhanced') {
                        setTimeout(function () {
                            self.initializeGeoControls();
                        }, 200);
                    }
                });
            }

            // Listen for element selection
            elementor.hooks.addAction('panel/open_editor/widget', function (panel, model) {
                setTimeout(function () {
                    self.loadElementRule(model);
                }, 300);
            });

            elementor.hooks.addAction('panel/open_editor/section', function (panel, model) {
                setTimeout(function () {
                    self.loadElementRule(model);
                }, 300);
            });

            elementor.hooks.addAction('panel/open_editor/container', function (panel, model) {
                setTimeout(function () {
                    self.loadElementRule(model);
                }, 300);
            });

            console.log('[EGP Enhanced] Elementor integration setup complete');
        },

        initializeGeoControls: function () {
            var self = this;
            
            // Auto-generate element ID if empty
            this.autoGenerateElementId();
            
            // Load existing rule data
            this.loadExistingRuleData();
            
            // Setup country selector
            this.setupCountrySelector();
            
            // Bind save events
            this.bindSaveEvents();
        },

        autoGenerateElementId: function () {
            var panel = elementor.getPanelView().getCurrentPageView();
            if (!panel || !panel.model) return;

            var settings = panel.model.get('settings');
            if (!settings) return;

            var elementId = settings.get('egp_element_id_enhanced');
            if (!elementId || elementId.trim() === '') {
                var newId = 'geo_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
                settings.set('egp_element_id_enhanced', newId);
                
                // Update the input field if it exists
                var $input = $('input[data-setting="egp_element_id_enhanced"]');
                if ($input.length) {
                    $input.val(newId);
                }
                
                console.log('[EGP Enhanced] Auto-generated element ID:', newId);
            }
        },

        loadExistingRuleData: function () {
            var panel = elementor.getPanelView().getCurrentPageView();
            if (!panel || !panel.model) return;

            var elementId = panel.model.get('id');
            
            $.post(egpEditorEnhanced.ajaxUrl, {
                action: 'egp_get_element_rule',
                nonce: egpEditorEnhanced.nonce,
                element_id: elementId
            }, function (response) {
                if (response.success && response.data) {
                    var rule = response.data;
                    var settings = panel.model.get('settings');
                    
                    if (settings) {
                        // Load rule data into controls
                        settings.set('egp_geo_enabled_enhanced', rule.active ? 'yes' : '');
                        settings.set('egp_element_id_enhanced', elementId);
                        settings.set('egp_priority_enhanced', rule.priority || 50);
                        
                        if (rule.countries && rule.countries.length > 0) {
                            settings.set('egp_countries_data', JSON.stringify(rule.countries));
                            
                            // Update country selector
                            setTimeout(function () {
                                var $select = $('#egp-countries-select');
                                if ($select.length) {
                                    $select.val(rule.countries);
                                    $select.trigger('change');
                                }
                            }, 100);
                        }
                        
                        console.log('[EGP Enhanced] Loaded existing rule data:', rule);
                    }
                }
            });
        },

        loadElementRule: function (model) {
            if (!model) return;
            
            var elementId = model.get('id');
            var self = this;
            
            // Small delay to ensure panel is ready
            setTimeout(function () {
                self.loadExistingRuleData();
            }, 500);
        },

        setupCountrySelector: function () {
            var self = this;
            
            // Enhanced country selector with search
            var $select = $('#egp-countries-select');
            if ($select.length && !$select.hasClass('egp-enhanced')) {
                $select.addClass('egp-enhanced');
                
                // Add search functionality
                this.addCountrySearch($select);
                
                // Bind change events
                $select.on('change', function () {
                    self.saveCountrySelection();
                });
                
                console.log('[EGP Enhanced] Country selector enhanced');
            }
        },

        addCountrySearch: function ($select) {
            var $wrapper = $select.parent();
            
            // Add search input
            var $search = $('<input type="text" placeholder="Search countries..." style="width: 100%; margin-bottom: 8px; padding: 4px 8px; border: 1px solid #ddd; border-radius: 3px;">');
            $select.before($search);
            
            // Filter options based on search
            $search.on('input', function () {
                var searchTerm = $(this).val().toLowerCase();
                
                $select.find('option').each(function () {
                    var optionText = $(this).text().toLowerCase();
                    var optionValue = $(this).val().toLowerCase();
                    
                    if (optionText.includes(searchTerm) || optionValue.includes(searchTerm)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
        },

        saveCountrySelection: function () {
            var panel = elementor.getPanelView().getCurrentPageView();
            if (!panel || !panel.model) return;

            var settings = panel.model.get('settings');
            if (!settings) return;

            var $select = $('#egp-countries-select');
            var selected = $select.val() || [];
            
            // Save to hidden field
            settings.set('egp_countries_data', JSON.stringify(selected));
            
            // Update display
            this.updateSelectedCountriesDisplay(selected);
            
            // Auto-save rule if enabled
            var enabled = settings.get('egp_geo_enabled_enhanced') === 'yes';
            if (enabled && selected.length > 0) {
                setTimeout(function () {
                    window.saveEnhancedRule();
                }, 1000);
            }
            
            console.log('[EGP Enhanced] Saved country selection:', selected);
        },

        updateSelectedCountriesDisplay: function (selected) {
            var $selectedDiv = $('#egp-selected-countries');
            var $selectedList = $('#egp-selected-list');
            
            if (selected.length > 0) {
                var names = selected.map(function (code) {
                    var option = $('#egp-countries-select option[value="' + code + '"]');
                    return option.length ? option.text() : code;
                });
                
                $selectedList.text(names.join(', '));
                $selectedDiv.show();
            } else {
                $selectedDiv.hide();
            }
        },

        bindSaveEvents: function () {
            var self = this;
            
            // Bind to enable/disable toggle
            $(document).on('change', 'input[data-setting="egp_geo_enabled_enhanced"]', function () {
                var enabled = $(this).is(':checked');
                console.log('[EGP Enhanced] Geo targeting enabled:', enabled);
                
                if (enabled) {
                    // Auto-generate ID if needed
                    self.autoGenerateElementId();
                }
            });
            
            // Bind to priority changes
            $(document).on('input change', 'input[data-setting="egp_priority_enhanced"]', function () {
                console.log('[EGP Enhanced] Priority changed:', $(this).val());
            });
        },

        // Utility functions
        getCurrentElementData: function () {
            var panel = elementor.getPanelView().getCurrentPageView();
            if (!panel || !panel.model) return null;

            var settings = panel.model.get('settings');
            if (!settings) return null;

            return {
                elementId: panel.model.get('id'),
                elementType: panel.model.get('elType') || 'widget',
                enabled: settings.get('egp_geo_enabled_enhanced') === 'yes',
                countries: this.getSelectedCountries(),
                priority: settings.get('egp_priority_enhanced') || 50,
                customElementId: settings.get('egp_element_id_enhanced')
            };
        },

        getSelectedCountries: function () {
            var panel = elementor.getPanelView().getCurrentPageView();
            if (!panel || !panel.model) return [];

            var settings = panel.model.get('settings');
            if (!settings) return [];

            var countriesData = settings.get('egp_countries_data');
            if (countriesData) {
                try {
                    return JSON.parse(countriesData);
                } catch (e) {
                    console.log('[EGP Enhanced] Error parsing countries data:', e);
                }
            }
            
            return [];
        },

        showNotification: function (message, type) {
            type = type || 'info';
            
            // Use Elementor's notification system if available
            if (elementor.notifications && elementor.notifications.showToast) {
                elementor.notifications.showToast({
                    message: message,
                    type: type
                });
            } else {
                // Fallback to console
                console.log('[EGP Enhanced] ' + type.toUpperCase() + ':', message);
            }
        }
    };

    // Global functions for inline scripts
    window.saveEnhancedRule = function () {
        var data = EGPEditorEnhanced.getCurrentElementData();
        if (!data) {
            EGPEditorEnhanced.showNotification('No element selected', 'error');
            return;
        }

        if (!data.enabled) {
            EGPEditorEnhanced.showNotification('Geo targeting is disabled', 'warning');
            return;
        }

        if (data.countries.length === 0) {
            EGPEditorEnhanced.showNotification('Please select at least one country', 'error');
            return;
        }

        var elementId = data.customElementId || data.elementId;
        
        // Auto-generate if still empty
        if (!elementId) {
            elementId = 'geo_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
            var panel = elementor.getPanelView().getCurrentPageView();
            if (panel && panel.model) {
                var settings = panel.model.get('settings');
                if (settings) {
                    settings.set('egp_element_id_enhanced', elementId);
                }
            }
        }

        var postData = {
            action: 'egp_save_elementor_rule_enhanced',
            nonce: egpEditorEnhanced.nonce,
            element_id: elementId,
            element_type: data.elementType,
            countries: data.countries,
            priority: data.priority,
            active: true,
            title: data.elementType.charAt(0).toUpperCase() + data.elementType.slice(1) + ' ' + elementId,
            document_id: egpEditorEnhanced.documentId || 0
        };

        window.showRuleStatus('loading', 'Saving rule...');

        $.post(egpEditorEnhanced.ajaxUrl, postData, function (response) {
            if (response.success) {
                var message = response.data.updated ? 'Rule updated successfully!' : 'Rule created successfully!';
                message += ' ID: ' + response.data.rule_id;
                window.showRuleStatus('success', message);
                EGPEditorEnhanced.showNotification(message, 'success');
            } else {
                var error = 'Save failed: ' + (response.data || 'Unknown error');
                window.showRuleStatus('error', error);
                EGPEditorEnhanced.showNotification(error, 'error');
            }
        }).fail(function () {
            var error = 'Network error occurred';
            window.showRuleStatus('error', error);
            EGPEditorEnhanced.showNotification(error, 'error');
        });
    };

    window.testEnhancedRule = function () {
        var data = EGPEditorEnhanced.getCurrentElementData();
        if (!data) {
            window.showRuleStatus('error', 'No element selected');
            return;
        }

        window.showRuleStatus('info', 'Testing: ' + data.countries.length + ' countries selected, priority: ' + data.priority);
    };

    // Initialize when document is ready
    $(document).ready(function () {
        EGPEditorEnhanced.init();
    });

    // Also initialize when Elementor is ready
    $(window).on('elementor:init', function () {
        setTimeout(function () {
            EGPEditorEnhanced.init();
        }, 1000);
    });

})(jQuery);