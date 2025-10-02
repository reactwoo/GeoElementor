/**
 * Geo Templates Admin
 * Handles template creation, editing, and management
 */

(function($) {
    'use strict';

    var EGPTemplates = {
        modal: null,
        form: null,

        init: function() {
            this.modal = $('#egp-template-modal');
            this.form = $('#egp-template-form');
            
            this.bindEvents();
            this.initSelect2();
        },

        bindEvents: function() {
            // New template button
            $('.egp-new-template').on('click', (e) => {
                e.preventDefault();
                this.openModal();
            });

            // Edit template button
            $(document).on('click', '.egp-edit-template', (e) => {
                e.preventDefault();
                const templateId = $(e.currentTarget).data('template-id');
                this.editTemplate(templateId);
            });

            // Delete template button
            $(document).on('click', '.egp-delete-template', (e) => {
                e.preventDefault();
                const templateId = $(e.currentTarget).data('template-id');
                this.deleteTemplate(templateId);
            });

            // Modal close
            $('.egp-modal-close').on('click', () => {
                this.closeModal();
            });

            // Click outside modal to close
            this.modal.on('click', (e) => {
                if ($(e.target).is('.egp-modal')) {
                    this.closeModal();
                }
            });

            // Form submit
            this.form.on('submit', (e) => {
                e.preventDefault();
                this.saveTemplate();
            });
        },

        initSelect2: function() {
            if ($.fn.select2) {
                $('#egp-template-countries').select2({
                    placeholder: 'Select countries...',
                    allowClear: true,
                    width: '100%'
                });
            }
        },

        openModal: function(templateData = null) {
            if (templateData) {
                // Edit mode
                $('#egp-modal-title').text('Edit Geo Template');
                $('#egp-template-id').val(templateData.id);
                $('#egp-template-name').val(templateData.name);
                $('#egp-template-type').val(templateData.type);
                $('#egp-template-fallback').val(templateData.fallback);
                
                if (templateData.countries) {
                    $('#egp-template-countries').val(templateData.countries).trigger('change');
                }
            } else {
                // New mode
                $('#egp-modal-title').text('New Geo Template');
                this.form[0].reset();
                $('#egp-template-id').val('');
                $('#egp-template-countries').val(null).trigger('change');
            }

            this.modal.fadeIn(200);
        },

        closeModal: function() {
            this.modal.fadeOut(200);
        },

        editTemplate: function(templateId) {
            $.ajax({
                url: egpTemplates.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'egp_get_geo_template',
                    nonce: egpTemplates.nonce,
                    template_id: templateId
                },
                success: (response) => {
                    if (response.success) {
                        this.openModal(response.data);
                    } else {
                        alert('Error loading template: ' + response.data);
                    }
                },
                error: () => {
                    alert('Network error loading template');
                }
            });
        },

        saveTemplate: function() {
            const formData = this.form.serializeArray();
            const data = {
                action: 'egp_save_geo_template',
                nonce: egpTemplates.nonce
            };

            // Convert form data to object
            formData.forEach(item => {
                if (item.name === 'template_countries[]') {
                    if (!data.template_countries) {
                        data.template_countries = [];
                    }
                    data.template_countries.push(item.value);
                } else {
                    data[item.name] = item.value;
                }
            });

            // Disable submit button
            const $submitBtn = this.form.find('button[type="submit"]');
            $submitBtn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: egpTemplates.ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        // Show success message
                        this.showNotice('Template saved successfully!', 'success');
                        
                        // If new template, offer to edit with Elementor
                        if (response.data.edit_url) {
                            if (confirm('Template created! Would you like to edit the content with Elementor now?')) {
                                window.open(response.data.edit_url, '_blank');
                            }
                        }
                        
                        // Close modal and reload page
                        this.closeModal();
                        setTimeout(() => {
                            location.reload();
                        }, 500);
                    } else {
                        this.showNotice('Error: ' + response.data, 'error');
                        $submitBtn.prop('disabled', false).text('Save Template');
                    }
                },
                error: () => {
                    this.showNotice('Network error saving template', 'error');
                    $submitBtn.prop('disabled', false).text('Save Template');
                }
            });
        },

        deleteTemplate: function(templateId) {
            if (!confirm('Are you sure you want to delete this template? This action cannot be undone.')) {
                return;
            }

            const $row = $(`tr[data-template-id="${templateId}"]`);
            $row.css('opacity', '0.5');

            $.ajax({
                url: egpTemplates.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'egp_delete_geo_template',
                    nonce: egpTemplates.nonce,
                    template_id: templateId
                },
                success: (response) => {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Check if table is now empty
                            if ($('.egp-templates-list tbody tr').length === 0) {
                                location.reload();
                            }
                        });
                        this.showNotice('Template deleted successfully', 'success');
                    } else {
                        $row.css('opacity', '1');
                        this.showNotice('Error: ' + response.data, 'error');
                    }
                },
                error: () => {
                    $row.css('opacity', '1');
                    this.showNotice('Network error deleting template', 'error');
                }
            });
        },

        showNotice: function(message, type = 'info') {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.egp-templates-wrap h1').after($notice);
            
            // Auto-dismiss after 3 seconds
            setTimeout(() => {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        EGPTemplates.init();
    });

})(jQuery);

