/**
 * Add-On Manager JavaScript
 * 
 * @package ElementorGeoPopup
 * @since 1.1.0
 */

(function($) {
    'use strict';
    
    var EGPAddonManager = {
        
        init: function() {
            this.bindEvents();
            this.initTabs();
        },
        
        bindEvents: function() {
            // Install add-on
            $(document).on('click', '.egp-install-addon', this.handleInstallAddon);
            
            // Activate add-on
            $(document).on('click', '.egp-activate-addon', this.handleActivateAddon);
            
            // Deactivate add-on
            $(document).on('click', '.egp-deactivate-addon', this.handleDeactivateAddon);
            
            // Uninstall add-on
            $(document).on('click', '.egp-uninstall-addon', this.handleUninstallAddon);
            
            // Update add-on
            $(document).on('click', '.egp-update-addon', this.handleUpdateAddon);
            
            // Tab switching
            $(document).on('click', '.egp-addons-tabs a', this.handleTabSwitch);
        },
        
        initTabs: function() {
            // Set active tab based on URL hash
            var hash = window.location.hash;
            if (hash) {
                this.switchTab(hash.substring(1));
            }
        },
        
        handleTabSwitch: function(e) {
            e.preventDefault();
            
            var target = $(this).attr('href');
            EGPAddonManager.switchTab(target.substring(1));
            
            // Update URL hash
            if (history.pushState) {
                history.pushState(null, null, target);
            }
        },
        
        switchTab: function(tabId) {
            // Update tab appearance
            $('.egp-addons-tabs .nav-tab').removeClass('nav-tab-active');
            $('.egp-addons-tabs a[href="#' + tabId + '"]').addClass('nav-tab-active');
            
            // Show/hide content
            $('.egp-tab-content').removeClass('active');
            $('#' + tabId).addClass('active');
        },
        
        handleInstallAddon: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var addonId = $button.data('addon');
            
            if (!confirm('Are you sure you want to install this add-on?')) {
                return;
            }
            
            EGPAddonManager.setButtonLoading($button, 'Installing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'egp_install_addon',
                    addon_id: addonId,
                    nonce: EGPAddonManager.getNonce()
                },
                success: function(response) {
                    if (response.success) {
                        EGPAddonManager.showNotice('success', response.data);
                        EGPAddonManager.refreshAddonList();
                    } else {
                        EGPAddonManager.showNotice('error', response.data || 'Installation failed');
                        EGPAddonManager.setButtonNormal($button, 'Install');
                    }
                },
                error: function() {
                    EGPAddonManager.showNotice('error', 'AJAX request failed');
                    EGPAddonManager.setButtonNormal($button, 'Install');
                }
            });
        },
        
        handleActivateAddon: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var addonId = $button.data('addon');
            
            EGPAddonManager.setButtonLoading($button, 'Activating...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'egp_activate_addon',
                    addon_id: addonId,
                    nonce: EGPAddonManager.getNonce()
                },
                success: function(response) {
                    if (response.success) {
                        EGPAddonManager.showNotice('success', response.data);
                        EGPAddonManager.refreshAddonList();
                    } else {
                        EGPAddonManager.showNotice('error', response.data || 'Activation failed');
                        EGPAddonManager.setButtonNormal($button, 'Activate');
                    }
                },
                error: function() {
                    EGPAddonManager.showNotice('error', 'AJAX request failed');
                    EGPAddonManager.setButtonNormal($button, 'Activate');
                }
            });
        },
        
        handleDeactivateAddon: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var addonId = $button.data('addon');
            
            if (!confirm('Are you sure you want to deactivate this add-on?')) {
                return;
            }
            
            EGPAddonManager.setButtonLoading($button, 'Deactivating...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'egp_deactivate_addon',
                    addon_id: addonId,
                    nonce: EGPAddonManager.getNonce()
                },
                success: function(response) {
                    if (response.success) {
                        EGPAddonManager.showNotice('success', response.data);
                        EGPAddonManager.refreshAddonList();
                    } else {
                        EGPAddonManager.showNotice('error', response.data || 'Deactivation failed');
                        EGPAddonManager.setButtonNormal($button, 'Deactivate');
                    }
                },
                error: function() {
                    EGPAddonManager.showNotice('error', 'AJAX request failed');
                    EGPAddonManager.setButtonNormal($button, 'Deactivate');
                }
            });
        },
        
        handleUninstallAddon: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var addonId = $button.data('addon');
            
            if (!confirm('Are you sure you want to uninstall this add-on? This action cannot be undone.')) {
                return;
            }
            
            EGPAddonManager.setButtonLoading($button, 'Uninstalling...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'egp_uninstall_addon',
                    addon_id: addonId,
                    nonce: EGPAddonManager.getNonce()
                },
                success: function(response) {
                    if (response.success) {
                        EGPAddonManager.showNotice('success', response.data);
                        EGPAddonManager.refreshAddonList();
                    } else {
                        EGPAddonManager.showNotice('error', response.data || 'Uninstallation failed');
                        EGPAddonManager.setButtonNormal($button, 'Uninstall');
                    }
                },
                error: function() {
                    EGPAddonManager.showNotice('error', 'AJAX request failed');
                    EGPAddonManager.setButtonNormal($button, 'Uninstall');
                }
            });
        },
        
        handleUpdateAddon: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var addonId = $button.data('addon');
            
            EGPAddonManager.setButtonLoading($button, 'Updating...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'egp_update_addon',
                    addon_id: addonId,
                    nonce: EGPAddonManager.getNonce()
                },
                success: function(response) {
                    if (response.success) {
                        EGPAddonManager.showNotice('success', response.data);
                        EGPAddonManager.refreshAddonList();
                    } else {
                        EGPAddonManager.showNotice('error', response.data || 'Update failed');
                        EGPAddonManager.setButtonNormal($button, 'Update');
                    }
                },
                error: function() {
                    EGPAddonManager.showNotice('error', 'AJAX request failed');
                    EGPAddonManager.setButtonNormal($button, 'Update');
                }
            });
        },
        
        setButtonLoading: function($button, text) {
            $button.prop('disabled', true)
                   .addClass('egp-loading')
                   .data('original-text', $button.text())
                   .text(text);
        },
        
        setButtonNormal: function($button, text) {
            $button.prop('disabled', false)
                   .removeClass('egp-loading')
                   .text(text);
        },
        
        showNotice: function(type, message) {
            var $notice = $('<div class="egp-notice notice-' + type + '"><p>' + message + '</p></div>');
            
            // Remove existing notices
            $('.egp-notice').remove();
            
            // Add new notice
            $('.wrap h1').after($notice);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            }, 5000);
        },
        
        refreshAddonList: function() {
            // Reload the page to refresh the add-on list
            // In a more sophisticated implementation, this would use AJAX to update the list
            setTimeout(function() {
                location.reload();
            }, 1000);
        },
        
        getNonce: function() {
            // Get nonce from a hidden field or data attribute
            return $('input[name="egp_addon_nonce"]').val() || 
                   $('body').data('egp-addon-nonce') || 
                   '';
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        EGPAddonManager.init();
    });
    
    // Make it globally available
    window.EGPAddonManager = EGPAddonManager;
    
})(jQuery);