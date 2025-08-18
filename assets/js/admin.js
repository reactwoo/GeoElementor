/**
 * Elementor Geo Popup - Admin JavaScript
 * 
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    $(document).ready(function () {

        // Database update functionality
        $('#egp-update-database').on('click', function () {
            var $button = $(this);
            var $status = $('#egp-status-message');

            $button.prop('disabled', true).text(egpAdmin.strings.updating);
            $status.html('<div class="notice notice-info"><p>' + egpAdmin.strings.updating + '</p></div>');

            $.post(egpAdmin.ajaxUrl, {
                action: 'egp_update_database',
                nonce: egpAdmin.nonce
            }, function (response) {
                if (response.success) {
                    $status.html('<div class="notice notice-success"><p>' + egpAdmin.strings.success + '</p></div>');
                    // Reload page after 2 seconds to show updated information
                    setTimeout(function () {
                        location.reload();
                    }, 2000);
                } else {
                    $status.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
                $button.prop('disabled', false).text('Update Database');
            }).fail(function () {
                $status.html('<div class="notice notice-error"><p>Request failed. Please try again.</p></div>');
                $button.prop('disabled', false).text('Update Database');
            });
        });

        // Test connection functionality
        $('#egp-test-connection').on('click', function () {
            var $button = $(this);
            var $status = $('#egp-status-message');

            $button.prop('disabled', true).text(egpAdmin.strings.testing);
            $status.html('<div class="notice notice-info"><p>' + egpAdmin.strings.testing + '</p></div>');

            $.post(egpAdmin.ajaxUrl, {
                action: 'egp_test_connection',
                nonce: egpAdmin.nonce
            }, function (response) {
                if (response.success) {
                    $status.html('<div class="notice notice-success"><p>' + egpAdmin.strings.connectionSuccess + '</p></div>');
                } else {
                    $status.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
                $button.prop('disabled', false).text('Test Connection');
            }).fail(function () {
                $status.html('<div class="notice notice-error"><p>Request failed. Please try again.</p></div>');
                $button.prop('disabled', false).text('Test Connection');
            });
        });

        // Auto-update toggle functionality
        $('input[name="egp_auto_update"]').on('change', function () {
            var isChecked = $(this).is(':checked');
            var $description = $(this).closest('td').find('.description');

            if (isChecked) {
                $description.html('Database will be automatically updated weekly. You can also manually update at any time.');
            } else {
                $description.html('Database updates must be performed manually.');
            }
        });

        // Debug mode toggle functionality
        $('input[name="egp_debug_mode"]').on('change', function () {
            var isChecked = $(this).is(':checked');
            var $description = $(this).closest('td').find('.description');

            if (isChecked) {
                $description.html('Debug mode is enabled. Geolocation data will be logged to the error log.');
            } else {
                $description.html('Debug mode is disabled. No geolocation data will be logged.');
            }
        });

        // Default popup ID validation
        $('#egp_default_popup_id').on('input', function () {
            var value = parseInt($(this).val());
            var $description = $(this).closest('td').find('.description');

            if (value < 0) {
                $(this).val(0);
                value = 0;
            }

            if (value === 0) {
                $description.html('No default popup will be shown when no country match is found.');
            } else {
                $description.html('Popup ID ' + value + ' will be shown when no country match is found.');
            }
        });

        // Fallback behavior change handler
        $('select[name="egp_fallback_behavior"]').on('change', function () {
            var value = $(this).val();
            var $description = $(this).closest('td').find('.description');

            switch (value) {
                case 'show_to_all':
                    $description.html('All visitors will see popups regardless of country.');
                    break;
                case 'show_to_none':
                    $description.html('No popups will be shown when no country match is found.');
                    break;
                case 'show_default':
                    $description.html('The default popup will be shown when no country match is found.');
                    break;
                default:
                    $description.html('Select a fallback behavior for when no country match is found.');
            }
        });

        // License key validation
        $('#egp_maxmind_license_key').on('input', function () {
            var value = $(this).val();
            var $description = $(this).closest('td').find('.description');

            if (value.length > 0) {
                if (value.length < 10) {
                    $description.html('License key appears to be too short. Please check your MaxMind license key.');
                } else {
                    $description.html('License key format looks good. You can now test the connection or update the database.');
                }
            } else {
                $description.html('Enter your MaxMind license key. You can get one for free at <a href="https://www.maxmind.com/en/geolite2/signup" target="_blank">maxmind.com</a>');
            }
        });

        // Settings form validation
        $('form').on('submit', function (e) {
            var licenseKey = $('#egp_maxmind_license_key').val();
            var databasePath = $('input[name="egp_database_path"]').val();

            if (licenseKey && !databasePath) {
                if (!confirm('You have entered a MaxMind license key but no database has been downloaded yet. Would you like to download the database after saving settings?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });

        // Initialize tooltips for better UX
        $('.egp-help-tip').tooltip({
            position: { my: 'left top', at: 'right+5 top-5' },
            tooltipClass: 'egp-tooltip'
        });

        // Auto-save functionality for better UX
        var autoSaveTimer;
        $('input, select').on('change', function () {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(function () {
                // Show auto-save indicator
                var $indicator = $('<div class="notice notice-info is-dismissible"><p>Auto-saving...</p></div>');
                $('.wrap h1').after($indicator);

                // Auto-dismiss after 2 seconds
                setTimeout(function () {
                    $indicator.fadeOut();
                }, 2000);
            }, 1000);
        });

        // Enhanced error handling
        $(document).ajaxError(function (event, xhr, settings, error) {
            var $status = $('#egp-status-message');
            var errorMessage = 'An unexpected error occurred. Please try again.';

            if (xhr.responseJSON && xhr.responseJSON.data) {
                errorMessage = xhr.responseJSON.data;
            } else if (xhr.statusText) {
                errorMessage = 'Request failed: ' + xhr.statusText;
            }

            $status.html('<div class="notice notice-error"><p>' + errorMessage + '</p></div>');
        });

        // Initialize any existing notices
        $('.notice').each(function () {
            var $notice = $(this);
            if ($notice.hasClass('is-dismissible')) {
                $notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');

                $notice.find('.notice-dismiss').on('click', function () {
                    $notice.fadeOut();
                });
            }
        });

    });

})(jQuery);



