/**
 * Elementor Library Geo Columns
 * Handles quick edit functionality
 */

(function($) {
    'use strict';

    // Populate quick edit fields when opened
    var $wp_inline_edit = inlineEditPost.edit;
    
    inlineEditPost.edit = function(id) {
        // Call original function
        $wp_inline_edit.apply(this, arguments);
        
        var post_id = 0;
        if (typeof(id) == 'object') {
            post_id = parseInt(this.getId(id));
        }
        
        if (post_id > 0) {
            // Get current row
            var $row = $('#post-' + post_id);
            
            // Get geo status from column
            var $geoStatus = $row.find('.egp-status-badge');
            var isEnabled = $geoStatus.hasClass('egp-enabled');
            
            // Get countries from column
            var $countriesCol = $row.find('.egp-countries-list');
            var countriesArray = [];

            if ($countriesCol.length) {
                var countriesText = $countriesCol.text().trim();
                // Remove the "+X" part if present
                countriesText = countriesText.replace(/\s*\+\d+\s*$/, '');
                // Split by comma and trim
                if (countriesText && countriesText !== '—') {
                    countriesArray = countriesText.split(',').map(function (c) {
                        return c.trim().toUpperCase();
                    });
                }
            }

            // Also check title attribute for full list if truncated
            var $moreCountries = $row.find('.egp-more-countries');
            if ($moreCountries.length && $moreCountries.attr('title')) {
                var fullList = $moreCountries.attr('title');
                countriesArray = fullList.split(',').map(function (c) {
                    return c.trim().toUpperCase();
                });
            }
            
            // Set quick edit values
            var $editRow = $('#edit-' + post_id);
            $editRow.find('select[name="egp_geo_enabled"]').val(isEnabled ? 'yes' : '');

            // Select countries in multi-select
            var $countrySelect = $editRow.find('select[name="egp_countries[]"]');
            $countrySelect.val(countriesArray);

            console.log('[EGP] Quick edit opened for post', post_id, 'Countries:', countriesArray);
        }
    };
    
    // Show admin notice after bulk action
    $(document).ready(function() {
        var urlParams = new URLSearchParams(window.location.search);
        
        if (urlParams.has('egp_bulk_action') && urlParams.has('egp_count')) {
            var action = urlParams.get('egp_bulk_action');
            var count = urlParams.get('egp_count');
            var message = '';
            
            if (action === 'egp_enable_geo') {
                message = 'Geo targeting enabled for ' + count + ' template(s).';
            } else if (action === 'egp_disable_geo') {
                message = 'Geo targeting disabled for ' + count + ' template(s).';
            }
            
            if (message) {
                var $notice = $('<div class="notice notice-success notice-egp-bulk is-dismissible"><p>' + message + '</p></div>');
                $('.wrap h1').after($notice);
                
                // Remove query params from URL
                var cleanUrl = window.location.pathname + '?post_type=elementor_library';
                window.history.replaceState({}, '', cleanUrl);
            }
        }
    });

})(jQuery);

