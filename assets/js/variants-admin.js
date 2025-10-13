/**
 * Variant Groups Admin JavaScript
 * Handles form submissions, AJAX calls, and dynamic country mapping management
 * 
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function () {
        try {
            RW_Geo_Variants_Admin.init();
            if (window.rwGeoVariants && rwGeoVariants.debug && window.console && console.log) {
                console.log('[RW_Geo_Variants_Admin] Initialized', {
                    addMappingButtons: $('.add-mapping').length,
                    mappingRows: $('.mapping-row').length
                });
            }
        } catch (e) { }
    });

    /**
     * Main admin functionality
     */
    var RW_Geo_Variants_Admin = {

        /**
         * Initialize admin functionality
         */
        init: function () {
            this.bindEvents();
            this.initFormHandling();
            this.initMappingHandling();
            this.applyTypeVisibility();
            this.wireDefaultTemplateEditors();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function () {
            // Form submission
            $('#variant-form').off('submit.rwgeo').on('submit.rwgeo', this.handleFormSubmit);

            // Delete variant buttons
            $(document).off('click.rwgeo', '.delete-variant').on('click.rwgeo', '.delete-variant', this.handleDeleteVariant);

            // Add mapping button
            $(document).off('click.rwgeo', '.add-mapping').on('click.rwgeo', '.add-mapping', this.handleAddMapping);

            // Save mapping buttons
            $(document).off('click.rwgeo', '.save-mapping').on('click.rwgeo', '.save-mapping', this.handleSaveMapping);

            // Delete mapping buttons
            $(document).off('click.rwgeo', '.delete-mapping').on('click.rwgeo', '.delete-mapping', this.handleDeleteMapping);

            // Auto-generate slug from name
            $('#variant_name').on('input', this.autoGenerateSlug);

            // Type mask change handling
            $('input[name="type_mask[]"]').off('change.rwgeo').on('change.rwgeo', this.handleTypeMaskChange);
        },

        /**
         * Initialize form handling
         */
        initFormHandling: function () {
            // Set up form validation if jQuery Validate is present; otherwise rely on HTML5 required attrs
            if ($.fn.validate && typeof $('#variant-form').validate === 'function') {
                $('#variant-form').validate({
                    rules: {
                        variant_name: { required: true, minlength: 2 },
                        variant_slug: { required: true, minlength: 2, pattern: /^[a-z0-9-]+$/ }
                    },
                    messages: {
                        variant_name: {
                            required: 'Variant group name is required',
                            minlength: 'Name must be at least 2 characters'
                        },
                        variant_slug: {
                            required: 'Slug is required',
                            minlength: 'Slug must be at least 2 characters',
                            pattern: 'Slug can only contain lowercase letters, numbers, and hyphens'

                        }
                    }
                });
            }
        },

        /**
         * Initialize mapping handling
         */
        initMappingHandling: function () {
            // Set up mapping row interactions
            this.setupMappingRows();
        },

        /**
         * Show/hide Page/Popup selects in mapping rows based on current type mask selections
         */
        applyTypeVisibility: function () {
            var pageChecked = $('input[name="type_mask[]"][value="' + this.getTypeMaskValue('page') + '"]').is(':checked');
            var popupChecked = $('input[name="type_mask[]"][value="' + this.getTypeMaskValue('popup') + '"]').is(':checked');
            $('.mapping-row .page-select').closest('tr').toggle(!!pageChecked);
            $('.mapping-row .popup-select').closest('tr').toggle(!!popupChecked);
            // Toggle Section/Widget ref rows
            var sectionChecked = $('input[name="type_mask[]"][value="' + this.getTypeMaskValue('section') + '"]').is(':checked');
            var widgetChecked = $('input[name="type_mask[]"][value="' + this.getTypeMaskValue('widget') + '"]').is(':checked');
            $('.mapping-row .section-ref-row').toggle(!!sectionChecked);
            $('.mapping-row .widget-ref-row').toggle(!!widgetChecked);

            // Also toggle default fields at the top of the group edit form
            try {
                var $tbl = $('table.form-table');
                // Default Page row label has "Default Page"
                $tbl.find('label:contains("Default Page")').closest('tr').toggle(!!pageChecked);
                // Default Popup row label has "Default Popup"
                $tbl.find('label:contains("Default Popup")').closest('tr').toggle(!!popupChecked);
                // Default Section/Widget rows
                $tbl.find('.egp-default-section-row').toggle(!!sectionChecked);
                $tbl.find('.egp-default-widget-row').toggle(!!widgetChecked);
            } catch (e) { }
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function (e) {
            e.preventDefault();

            var $form = $(this);
            var $submitButton = $form.find('#submit');
            var originalText = $submitButton.val();

            // Validate form (supports both jQuery Validate and native constraints)
            if ($.fn.validate && typeof $form.valid === 'function') {
                if (!$form.valid()) {
                    return false;
                }
            } else if (!$form[0].checkValidity()) {
                $form[0].reportValidity && $form[0].reportValidity();
                return false;
            }

            // Disable submit button and show loading state
            $submitButton.prop('disabled', true).val(rwGeoVariants.strings.saving);

            // Collect form data
            var formData = new FormData($form[0]);
            formData.append('action', 'rw_geo_save_variant');
            formData.append('nonce', rwGeoVariants.nonce);

            // Check if this is create or update
            var isCreate = !$form.find('input[name="variant_id"]').val();

            // Submit via AJAX
            $.ajax({
                url: rwGeoVariants.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        // Show success message
                        RW_Geo_Variants_Admin.showNotice(response.data.message, 'success');

                        // If this was a new variant, redirect to edit page immediately
                        if (isCreate && response.data.variant_id) {
                            $submitButton.val('Redirecting...');
                            // Immediate redirect to edit page with country mapping fields
                            window.location.href = 'admin.php?page=geo-elementor-variants&action=edit&id=' + response.data.variant_id;
                        } else {
                            // Update - just reload to show changes
                            setTimeout(function () {
                                window.location.reload();
                            }, 1000);
                        }
                    } else {
                        RW_Geo_Variants_Admin.showNotice(response.data || rwGeoVariants.strings.error, 'error');
                        $submitButton.prop('disabled', false).val(originalText);
                    }
                },
                error: function () {
                    RW_Geo_Variants_Admin.showNotice(rwGeoVariants.strings.error, 'error');
                    $submitButton.prop('disabled', false).val(originalText);
                }
            });
        },

        /**
         * Handle delete variant
         */
        handleDeleteVariant: function (e) {
            e.preventDefault();

            var $button = $(this);
            var variantId = $button.data('id');

            if (!confirm(rwGeoVariants.strings.confirmDelete)) {
                return;
            }

            $button.prop('disabled', true);

            $.ajax({
                url: rwGeoVariants.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rw_geo_delete_variant',
                    variant_id: variantId,
                    nonce: rwGeoVariants.nonce
                },
                success: function (response) {
                    if (response.success) {
                        // Remove row from table
                        $button.closest('tr').fadeOut(300, function () {
                            $(this).remove();

                            // Check if table is empty
                            if ($('.wp-list-table tbody tr').length === 0) {
                                location.reload();
                            }
                        });

                        RW_Geo_Variants_Admin.showNotice(response.data, 'success');
                    } else {
                        RW_Geo_Variants_Admin.showNotice(response.data || rwGeoVariants.strings.error, 'error');

                        $button.prop('disabled', false);
                    }
                },
                error: function () {
                    RW_Geo_Variants_Admin.showNotice(rwGeoVariants.strings.error, 'error');
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Handle add mapping
         */
        handleAddMapping: function (e) {
            e.preventDefault();
            if (window.rwGeoVariants && rwGeoVariants.debug && window.console && console.log) {
                console.log('[RW_Geo_Variants_Admin] Add mapping clicked');
            }

            var $container = $('#country-mappings');
            var $template = $('#mapping-template');
            var templateHtml = $template.length ? ($template.html() || '').trim() : '';
            if (!templateHtml || templateHtml.length === 0) {
                // Fallback: construct minimal row if template missing
                templateHtml = [
                    '<table class="form-table">',
                    '<tr><th><label>Country</label></th><td><select class="country-select" required><option value="">Select Country</option></select></td></tr>',
                    '<tr class="page-row"><th><label>Page</label></th><td><select class="page-select"><option value="">Use Default</option></select></td></tr>',
                    '<tr class="popup-row"><th><label>Popup</label></th><td><select class="popup-select"><option value="">Use Default</option></select></td></tr>',
                    '<tr><th></th><td><button type="button" class="button button-small save-mapping" data-id="{{id}}">Save Mapping</button> ',
                    '<button type="button" class="button button-small button-link-delete delete-mapping" data-id="{{id}}">Delete</button></td></tr>',
                    '</table>'
                ].join('');
            }

            // If template includes a wrapper .mapping-row (from PHP), strip it and use its inner HTML
            (function () {
                try {
                    var $wrap = $('<div/>').html(templateHtml);
                    var $tplRow = $wrap.find('.mapping-row').first();
                    if ($tplRow.length) {
                        templateHtml = $tplRow.html();
                    }
                } catch (err) { }
            })();

            // Generate unique ID for new mapping
            var newId = 'new_' + Date.now();
            var newHtml = templateHtml.replace(/\{\{id\}\}/g, newId);

            // Add new mapping row
            $container.append('<div class="mapping-row" id="mapping-row-' + newId + '">' + newHtml + '</div>');
            var $newRow = $('#mapping-row-' + newId);
            $newRow.css('display', 'block');
            $newRow.find('tr').css('display', 'table-row');

            if (window.rwGeoVariants && rwGeoVariants.debug && window.console && console.log) {
                console.log('[RW_Geo_Variants_Admin] Mapping row added', { id: newId, usedTemplate: !!templateHtml, htmlLen: (newHtml || '').length });
            }


            // Initialize the new row
            RW_Geo_Variants_Admin.setupMappingRow($('#mapping-row-' + newId));
            RW_Geo_Variants_Admin.applyTypeVisibility();

            // Scroll to new row
            $('html, body').animate({
                scrollTop: $('#mapping-row-' + newId).offset().top - 100
            }, 500);
        },

        /**
         * Handle save mapping
         */
        handleSaveMapping: function (e) {
            e.preventDefault();

            var $button = $(this);
            var mappingId = $button.data('id');
            var $row = $button.closest('.mapping-row');

            // Get selected countries from multi-select
            var selectedCountries = $row.find('.country-select').val();

            // Enhanced validation for countries
            if (!selectedCountries || !Array.isArray(selectedCountries) || selectedCountries.length === 0) {
                RW_Geo_Variants_Admin.showNotice('Please select at least one country', 'error');
                $row.find('.country-select').focus();
                return;
            }

            // Filter out empty values
            selectedCountries = selectedCountries.filter(function (country) {
                return country && country.trim() !== '';
            });

            if (selectedCountries.length === 0) {
                RW_Geo_Variants_Admin.showNotice('Please select valid countries', 'error');
                $row.find('.country-select').focus();
                return;
            }

            // Collect mapping data
            var mappingData = {
                action: 'rw_geo_save_mapping',
                nonce: rwGeoVariants.nonce,
                variant_id: $('input[name="variant_id"]').val(),
                countries: selectedCountries, // Array of countries
                country_iso2: selectedCountries[0], // Backwards compat
                page_id: $row.find('.page-select').val() || '',
                popup_id: $row.find('.popup-select').val() || '',
                section_ref: ($row.find('.section-ref').val() || '').replace(/^#/, ''),
                widget_ref: ($row.find('.widget-ref').val() || '').replace(/^#/, '')
            };

            // Additional validation
            if (!mappingData.variant_id) {
                RW_Geo_Variants_Admin.showNotice('Group ID is missing. Please reload the page.', 'error');
                return;
            }

            // Disable button and show loading
            var originalText = $button.text();
            $button.prop('disabled', true).text(rwGeoVariants.strings.saving);

            $.ajax({
                url: rwGeoVariants.ajaxurl,
                type: 'POST',
                data: mappingData,
                success: function (response) {
                    if (response.success) {
                        RW_Geo_Variants_Admin.showNotice(response.data.message, 'success');
                        $button.text('Saved!').addClass('button-primary');

                        // Reload page to show updated data
                        setTimeout(function () {
                            window.location.reload();
                        }, 1000);
                    } else {
                        RW_Geo_Variants_Admin.showNotice(response.data || rwGeoVariants.strings.error, 'error');
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function () {
                    RW_Geo_Variants_Admin.showNotice(rwGeoVariants.strings.error, 'error');
                },
                complete: function () {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Handle delete mapping
         */
        handleDeleteMapping: function (e) {
            e.preventDefault();

            var $button = $(this);
            var mappingId = $button.data('id');
            var $row = $button.closest('.mapping-row');

            if (!confirm(rwGeoVariants.strings.confirmDeleteMapping)) {
                return;
            }

            // If this is a new mapping (not saved yet), just remove the row
            if (mappingId.toString().startsWith('new_')) {
                $row.fadeOut(300, function () {
                    $(this).remove();
                });
                return;
            }

            $button.prop('disabled', true);

            $.ajax({
                url: rwGeoVariants.ajaxurl,
                type: 'POST',
                data: {
                    action: 'rw_geo_delete_mapping',
                    mapping_id: mappingId,
                    nonce: rwGeoVariants.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $row.fadeOut(300, function () {
                            $(this).remove();
                        });

                        RW_Geo_Variants_Admin.showNotice(response.data, 'success');
                    } else {
                        RW_Geo_Variants_Admin.showNotice(response.data || rwGeoVariants.strings.error, 'error');

                        $button.prop('disabled', false);
                    }
                },
                error: function () {
                    RW_Geo_Variants_Admin.showNotice(rwGeoVariants.strings.error, 'error');
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Auto-generate slug from name
         */
        autoGenerateSlug: function () {
            var name = $(this).val();
            var slug = name.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .trim();

            $('#variant_slug').val(slug);
        },

        /**
         * Handle type mask changes
         */
        handleTypeMaskChange: function () {
            RW_Geo_Variants_Admin.applyTypeVisibility();
        },

        /**
         * Get type mask value for a specific type
         */
        getTypeMaskValue: function (type) {
            var types = {
                'page': 1,
                'popup': 2,
                'section': 4,
                'widget': 8
            };
            return types[type] || 0;
        },

        /**
         * Setup mapping rows
         */
        setupMappingRows: function () {
            $('.mapping-row').each(function () {
                RW_Geo_Variants_Admin.setupMappingRow($(this));
            });
        },

        /**
         * Setup individual mapping row
         */
        setupMappingRow: function ($row) {
            // Use native selects to avoid conflicts with non-standard libraries
            $row.find('.country-select').attr('required', true);

            // Inline buffered type-to-filter for native select (no separate field)
            var $country = $row.find('.country-select');
            if ($country.length) {
                // Preload full list once
                if ($country.data('rwgeo-loaded') !== true) {
                    $.ajax({
                        url: rwGeoVariants.ajaxurl,
                        method: 'GET',
                        data: { action: 'rw_geo_search_countries', nonce: rwGeoVariants.nonce, q: '' },
                        success: function (resp) {
                            if (resp && resp.success && Array.isArray(resp.data)) {
                                var current = $country.val();
                                var html = ['<option value="">Select Country</option>'];
                                resp.data.forEach(function (item) {
                                    var sel = (current === item.code) ? ' selected' : '';
                                    html.push('<option value="' + item.code + '"' + sel + '>' + item.name + '</option>');
                                });
                                $country.html(html.join(''));
                                $country.data('rwgeo-loaded', true);
                            }
                        }
                    });
                }

                // Buffered key search on the select itself
                var buffer = '';
                var lastKeyTime = 0;
                $country.on('keydown', function (e) {
                    var now = Date.now();
                    if (now - lastKeyTime > 700) {
                        buffer = '';
                    }
                    lastKeyTime = now;
                    var key = e.key;
                    if (key.length === 1 && /[a-zA-Z\s]/.test(key)) {
                        buffer += key.toLowerCase();
                        var found = false;
                        $country.find('option').each(function () {
                            var text = $(this).text().toLowerCase();
                            if (text.indexOf(buffer) === 0) {
                                $country.val($(this).val());
                                found = true;
                                return false;
                            }
                        });
                        if (found) {
                            e.preventDefault();
                            $country.trigger('change');
                        }
                    }
                });
            }
        },

        /**
         * Wire default template edit buttons
         */
        wireDefaultTemplateEditors: function () {
            try {
                var $secSel = $('#default_section_tpl');
                var $widSel = $('#default_widget_tpl');
                var $secBtn = $('#egp_default_section_edit_tpl');
                var $widBtn = $('#egp_default_widget_edit_tpl');
                function toggleBtn($btn, $sel) { try { var v = ($sel.val() || ''); var enabled = v.indexOf('template:') === 0; $btn.prop('disabled', !enabled).css('opacity', enabled ? 1 : 0.6); } catch (e) { } }
                toggleBtn($secBtn, $secSel); toggleBtn($widBtn, $widSel);
                $secSel.on('change', function () { toggleBtn($secBtn, $secSel); });
                $widSel.on('change', function () { toggleBtn($widBtn, $widSel); });

                $secBtn.off('click.rwgeo').on('click.rwgeo', function (e) {
                    e.preventDefault();
                    var v = ($secSel.val() || '');
                    if (v.indexOf('template:') === 0) { var id = parseInt(v.replace('template:', '').replace(/\D+/g, ''), 10); if (id > 0) { window.open(ajaxurl.replace('admin-ajax.php', 'post.php?post=' + id + '&action=elementor'), '_blank'); } }
                });
                $widBtn.off('click.rwgeo').on('click.rwgeo', function (e) {
                    e.preventDefault();
                    var v = ($widSel.val() || '');
                    if (v.indexOf('template:') === 0) { var id = parseInt(v.replace('template:', '').replace(/\D+/g, ''), 10); if (id > 0) { window.open(ajaxurl.replace('admin-ajax.php', 'post.php?post=' + id + '&action=elementor'), '_blank'); } }
                });
            } catch (e) { }
        },

        /**
         * Show admin notice
         */
        showNotice: function (message, type) {
            type = type || 'info';

            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');


            // Remove existing notices of same type
            $('.notice-' + type).remove();

            // Add new notice
            $('.wrap h1').after($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(function () {
                $notice.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 5000);

            // Make dismissible
            $notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');

            $notice.find('.notice-dismiss').on('click', function () {
                $notice.fadeOut(300, function () {
                    $(this).remove();
                });
            });
        }
    };

})(jQuery);
