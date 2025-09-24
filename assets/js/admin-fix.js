/**
 * Admin Fix for Geo Rules Management
 * Fixes admin rule editing and "Edit in Elementor" functionality
 */

(function ($) {
    'use strict';

    var EGPAdminFix = {
        init: function () {
            this.bindEvents();
            this.fixEditInElementorButtons();
            console.log('[EGP Admin Fix] Loaded');
        },

        bindEvents: function () {
            var self = this;
            
            // Fix target type selection
            $(document).on('change', '#egp_target_type', function () {
                self.updateTargetOptions();
            });
            
            // Fix "Edit in Elementor" buttons
            $(document).on('click', '.egp-edit-in-elementor', function (e) {
                e.preventDefault();
                self.handleEditInElementor($(this));
            });
            
            // Enhanced country selection
            this.enhanceCountrySelector();
            
            // Fix form submission
            this.fixFormSubmission();
        },

        updateTargetOptions: function () {
            var targetType = $('#egp_target_type').val();
            var $targetSelection = $('#egp_target_selection');
            var $targetIdField = $('#egp_target_id');
            
            if (!targetType) {
                $targetSelection.html('<p class="description">Select a target type first</p>');
                return;
            }
            
            $targetSelection.html('<p class="description">Loading options...</p>');
            
            $.post(egpAdminFix.ajaxUrl, {
                action: 'egp_get_target_options',
                target_type: targetType,
                nonce: egpAdminFix.nonce
            }, function (response) {
                if (response.success) {
                    EGPAdminFix.renderTargetOptions(targetType, response.data, $targetIdField.val());
                } else {
                    $targetSelection.html('<p class="description">Error loading options</p>');
                }
            });
        },

        renderTargetOptions: function (targetType, options, selectedValue) {
            var $targetSelection = $('#egp_target_selection');
            var html = '';
            
            if (targetType === 'page' || targetType === 'popup') {
                html = '<select name="egp_target_id_select" id="egp_target_id_select" style="width: 100%;">';
                html += '<option value="">Select a ' + targetType + '</option>';
                
                if (targetType === 'page') {
                    html += '<option value="all" ' + (selectedValue === 'all' ? 'selected' : '') + '>All Pages</option>';
                } else {
                    html += '<option value="all" ' + (selectedValue === 'all' ? 'selected' : '') + '>All Popups</option>';
                }
                
                options.forEach(function (option) {
                    html += '<option value="' + option.id + '" ' + (selectedValue == option.id ? 'selected' : '') + '>' + option.title + '</option>';
                });
                html += '</select>';
                
                // Add "Edit in Elementor" button for popups
                if (targetType === 'popup') {
                    html += '<br><br><button type="button" class="button egp-edit-in-elementor" data-target-type="popup" data-target-id="' + selectedValue + '">Edit in Elementor</button>';
                }
                
            } else if (targetType === 'section' || targetType === 'widget') {
                html = this.renderElementTargetOptions(targetType, options, selectedValue);
            }
            
            $targetSelection.html(html);
            
            // Bind change event
            $('#egp_target_id_select').on('change', function () {
                $('#egp_target_id').val($(this).val());
                
                // Update edit button
                var $editBtn = $('.egp-edit-in-elementor');
                if ($editBtn.length) {
                    $editBtn.attr('data-target-id', $(this).val());
                }
            });
        },

        renderElementTargetOptions: function (targetType, options, selectedValue) {
            var html = '<div class="egp-element-target-options">';
            
            // Manual ID input
            html += '<div style="margin-bottom: 15px;">';
            html += '<label><strong>Target by Element ID or CSS ID:</strong></label><br>';
            html += '<input type="text" id="egp_element_ref" placeholder="element-id or css-id" value="' + (selectedValue || '') + '" style="width: 70%;">';
            html += '<button type="button" class="button egp-edit-in-elementor" data-target-type="' + targetType + '" style="margin-left: 10px;">Edit in Elementor</button>';
            html += '<p class="description">Enter the Elementor element ID or CSS ID (without #)</p>';
            html += '</div>';
            
            // Template selector if available
            if (options && options.length > 0) {
                html += '<div>';
                html += '<label><strong>Or select a template:</strong></label><br>';
                html += '<select id="egp_template_select" style="width: 70%;">';
                html += '<option value="">Select a template</option>';
                
                options.forEach(function (option) {
                    var templateValue = 'template:' + option.id;
                    html += '<option value="' + templateValue + '" ' + (selectedValue === templateValue ? 'selected' : '') + '>' + option.title + '</option>';
                });
                
                html += '</select>';
                html += '<button type="button" class="button egp-edit-template" style="margin-left: 10px;">Edit Template</button>';
                html += '</div>';
            }
            
            html += '</div>';
            
            return html;
        },

        handleEditInElementor: function ($button) {
            var targetType = $button.data('target-type');
            var targetId = $button.data('target-id') || $('#egp_element_ref').val() || $('#egp_target_id_select').val();
            
            if (!targetId) {
                alert('Please select or enter a target ID first');
                return;
            }
            
            var url = '';
            
            if (targetType === 'popup') {
                // Direct link to popup editor
                url = egpAdminFix.elementorUrl + targetId;
            } else if (targetType === 'section' || targetType === 'widget') {
                // Try to determine the document ID
                var documentId = this.getDocumentIdForElement(targetId);
                if (documentId) {
                    url = egpAdminFix.elementorUrl + documentId;
                    if (targetId && !targetId.startsWith('template:')) {
                        url += '#element-' + targetId;
                    }
                } else {
                    alert('Cannot determine the document for this element. Please edit manually.');
                    return;
                }
            }
            
            if (url) {
                window.open(url, '_blank');
            }
        },

        getDocumentIdForElement: function (elementId) {
            // This would need to be enhanced to actually find the document
            // For now, return null to indicate we can't determine it
            return null;
        },

        enhanceCountrySelector: function () {
            var $countrySelect = $('#egp_countries');
            if ($countrySelect.length) {
                // Add search functionality
                this.addCountrySearch($countrySelect);
                
                // Add select all/none buttons
                this.addCountryButtons($countrySelect);
            }
        },

        addCountrySearch: function ($select) {
            var $wrapper = $('<div class="egp-country-selector-wrapper"></div>');
            $select.wrap($wrapper);
            
            var $search = $('<input type="text" placeholder="Search countries..." style="width: 100%; margin-bottom: 8px; padding: 4px 8px;">');
            $select.before($search);
            
            $search.on('input', function () {
                var searchTerm = $(this).val().toLowerCase();
                
                $select.find('option').each(function () {
                    var optionText = $(this).text().toLowerCase();
                    if (optionText.includes(searchTerm)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
        },

        addCountryButtons: function ($select) {
            var $buttons = $('<div style="margin-top: 8px;"></div>');
            var $selectAll = $('<button type="button" class="button button-small">Select All</button>');
            var $selectNone = $('<button type="button" class="button button-small" style="margin-left: 8px;">Select None</button>');
            
            $selectAll.on('click', function () {
                $select.find('option:visible').prop('selected', true);
            });
            
            $selectNone.on('click', function () {
                $select.find('option').prop('selected', false);
            });
            
            $buttons.append($selectAll).append($selectNone);
            $select.after($buttons);
        },

        fixFormSubmission: function () {
            var $form = $('form#post');
            if ($form.length) {
                $form.on('submit', function (e) {
                    var targetType = $('#egp_target_type').val();
                    var targetId = $('#egp_target_id').val();
                    var targetIdSelect = $('#egp_target_id_select').val();
                    var elementRef = $('#egp_element_ref').val();
                    
                    // Ensure target_id is set from the appropriate source
                    if (!targetId) {
                        if (targetIdSelect) {
                            $('#egp_target_id').val(targetIdSelect);
                        } else if (elementRef) {
                            $('#egp_target_id').val(elementRef);
                        }
                    }
                    
                    // Validate required fields
                    if (targetType && !$('#egp_target_id').val()) {
                        e.preventDefault();
                        alert('Please select or enter a target before saving.');
                        return false;
                    }
                });
            }
        },

        fixEditInElementorButtons: function () {
            // Fix existing "Edit in Elementor" buttons on page load
            $('.egp-edit-in-elementor').each(function () {
                var $this = $(this);
                if (!$this.data('fixed')) {
                    $this.data('fixed', true);
                    
                    // Update button behavior based on context
                    var targetType = $this.closest('tr').find('#egp_target_type').val();
                    if (targetType) {
                        $this.attr('data-target-type', targetType);
                    }
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function () {
        EGPAdminFix.init();
    });

})(jQuery);