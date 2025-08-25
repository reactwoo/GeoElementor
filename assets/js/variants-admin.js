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
        RW_Geo_Variants_Admin.init();
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
        },

        /**
         * Bind event handlers
         */
        bindEvents: function () {
            // Form submission
            $('#variant-form').on('submit', this.handleFormSubmit);

            // Delete variant buttons
            $(document).on('click', '.delete-variant', this.handleDeleteVariant);

            // Add mapping button
            $(document).on('click', '.add-mapping', this.handleAddMapping);

            // Save mapping buttons
            $(document).on('click', '.save-mapping', this.handleSaveMapping);

            // Delete mapping buttons
            $(document).on('click', '.delete-mapping', this.handleDeleteMapping);

            // Auto-generate slug from name
            $('#variant_name').on('input', this.autoGenerateSlug);

            // Type mask change handling
            $('input[name="type_mask[]"]').on('change', this.handleTypeMaskChange);
        },

        /**
         * Initialize form handling
         */
        initFormHandling: function () {
            // Set up form validation and submission
            $('#variant-form').validate({
                rules: {
                    variant_name: {
                        required: true,
                        minlength: 2
                    },
                    variant_slug: {
                        required: true,
                        minlength: 2,
                        pattern: /^[a-z0-9-]+$/
                    }
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
        },

        /**
         * Initialize mapping handling
         */
        initMappingHandling: function () {
            // Set up mapping row interactions
            this.setupMappingRows();
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function (e) {
            e.preventDefault();

            var $form = $(this);
            var $submitButton = $form.find('#submit');
            var originalText = $submitButton.val();

            // Validate form
            if (!$form.valid()) {
                return false;
            }

            // Disable submit button and show loading state
            $submitButton.prop('disabled', true).val(rwGeoVariants.strings.saving);

            // Collect form data
            var formData = new FormData($form[0]);
            formData.append('action', 'rw_geo_save_variant');
            formData.append('nonce', rwGeoVariants.nonce);

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

                        // If this was a new variant, redirect to edit page
                        if (!formData.get('variant_id')) {
                            setTimeout(function () {
                                window.location.href = 'admin.php?page=geo-elementor-variants&action=edit&id=' + response.data.variant_id;
                            }, 1500);
                        }
                    } else {
                        RW_Geo_Variants_Admin.showNotice(response.data || rwGeoVariants.strings.error, 'error');
                    }
                },
                error: function () {
                    RW_Geo_Variants_Admin.showNotice(rwGeoVariants.strings.error, 'error');
                },
                complete: function () {
                    // Re-enable submit button
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

            var $container = $('#country-mappings');
            var $template = $('#mapping-template');
            var templateHtml = $template.html();

            // Generate unique ID for new mapping
            var newId = 'new_' + Date.now();
            var newHtml = templateHtml.replace(/\{\{id\}\}/g, newId);

            // Add new mapping row
            $container.append('<div class="mapping-row" id="mapping-row-' + newId + '">' + newHtml + '</div>');

            // Initialize the new row
            RW_Geo_Variants_Admin.setupMappingRow($('#mapping-row-' + newId));

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

            // Collect mapping data
            var mappingData = {
                action: 'rw_geo_save_mapping',
                nonce: rwGeoVariants.nonce,
                variant_id: $('input[name="variant_id"]').val(),
                country_iso2: $row.find('.country-select').val(),
                page_id: $row.find('.page-select').val() || '',
                popup_id: $row.find('.popup-select').val() || ''
            };

            // Validate required fields
            if (!mappingData.country_iso2) {
                RW_Geo_Variants_Admin.showNotice('Country is required', 'error');
                return;
            }

            // Disable button and show loading
            $button.prop('disabled', true).text(rwGeoVariants.strings.saving);

            $.ajax({
                url: rwGeoVariants.ajaxurl,
                type: 'POST',
                data: mappingData,
                success: function (response) {
                    if (response.success) {
                        // Update row ID if this was a new mapping
                        if (mappingId.toString().startsWith('new_')) {
                            $row.attr('id', 'mapping-row-' + response.data.mapping_id);
                            $button.data('id', response.data.mapping_id);
                        }

                        RW_Geo_Variants_Admin.showNotice(response.data.message, 'success');
                        $button.text('Saved!').addClass('button-primary');

                        // Reset button after delay
                        setTimeout(function () {
                            $button.text('Save Mapping').removeClass('button-primary');
                        }, 2000);
                    } else {
                        RW_Geo_Variants_Admin.showNotice(response.data || rwGeoVariants.strings.error, 'error');
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
            var $row = $(this).closest('tr');
            var $nextRow = $row.next('tr');

            // Show/hide related form fields based on type selection
            if ($(this).val() == RW_Geo_Variants_Admin.getTypeMaskValue('page')) {
                $nextRow.toggle($(this).is(':checked'));
            }
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
            // Initialize select2 if available
            if ($.fn.select2) {
                $row.find('.country-select, .page-select, .popup-select').select2({
                    width: '100%',
                    placeholder: 'Select...'
                });
            }

            // Add validation
            $row.find('.country-select').attr('required', true);
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
