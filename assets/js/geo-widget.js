/**
 * Geo Widget JavaScript - Popup System
 * Fixed infinite loop issue and proper popup management
 */

(function($) {
    'use strict';
    
    var EGP_Popup = {
        isInitialized: false,
        retryCount: 0,
        maxRetries: 3,
        retryDelay: 2000,
        
        init: function() {
            if (this.isInitialized) {
                return;
            }
            
            console.log('[EGP] Initializing popup system...');
            
            // Check if we can initialize
            if (this.canInitialize()) {
                this.setupPopupSystem();
                this.isInitialized = true;
                console.log('[EGP] Popup system initialized successfully');
            } else {
                this.scheduleRetry();
            }
        },
        
        canInitialize: function() {
            // Check if required dependencies are available
            return (
                typeof $ !== 'undefined' &&
                $('.egp-popup-container').length > 0 &&
                typeof this.showPopup === 'function'
            );
        },
        
        setupPopupSystem: function() {
            try {
                // Bind popup events
                this.bindPopupEvents();
                
                // Initialize any existing popups
                this.initializeExistingPopups();
                
                console.log('[EGP] Popup system setup complete');
            } catch (error) {
                console.error('[EGP] Error setting up popup system:', error);
            }
        },
        
        bindPopupEvents: function() {
            // Close button events
            $(document).on('click', '.egp-popup-close', function(e) {
                e.preventDefault();
                EGP_Popup.hidePopup($(this).closest('.egp-popup'));
            });
            
            // Overlay click to close
            $(document).on('click', '.egp-popup-overlay', function(e) {
                if (e.target === this) {
                    EGP_Popup.hidePopup($(this).find('.egp-popup'));
                }
            });
            
            // ESC key to close
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27) { // ESC key
                    EGP_Popup.hideAllPopups();
                }
            });
        },
        
        initializeExistingPopups: function() {
            $('.egp-popup').each(function() {
                var $popup = $(this);
                var popupId = $popup.attr('id');
                
                if (popupId) {
                    console.log('[EGP] Found existing popup:', popupId);
                }
            });
        },
        
        showPopup: function(popupId, options) {
            if (!this.isInitialized) {
                console.warn('[EGP] Popup system not initialized yet');
                return false;
            }
            
            try {
                var $popup = $('#' + popupId);
                
                if ($popup.length === 0) {
                    console.error('[EGP] Popup not found:', popupId);
                    return false;
                }
                
                // Hide any existing popups
                this.hideAllPopups();
                
                // Show the target popup
                $popup.addClass('egp-popup-active');
                $('body').addClass('egp-popup-open');
                
                // Track popup view
                this.trackPopupView(popupId);
                
                console.log('[EGP] Popup shown:', popupId);
                return true;
                
            } catch (error) {
                console.error('[EGP] Error showing popup:', error);
                return false;
            }
        },
        
        hidePopup: function($popup) {
            if (!$popup || $popup.length === 0) {
                return;
            }
            
            $popup.removeClass('egp-popup-active');
            $('body').removeClass('egp-popup-open');
            
            console.log('[EGP] Popup hidden:', $popup.attr('id'));
        },
        
        hideAllPopups: function() {
            $('.egp-popup').removeClass('egp-popup-active');
            $('body').removeClass('egp-popup-open');
            console.log('[EGP] All popups hidden');
        },
        
        trackPopupView: function(popupId) {
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
        
        scheduleRetry: function() {
            if (this.retryCount >= this.maxRetries) {
                console.error('[EGP] Max retries exceeded. Popup system initialization failed.');
                return;
            }
            
            this.retryCount++;
            console.log('[EGP] Scheduling retry ' + this.retryCount + '/' + this.maxRetries + ' in ' + this.retryDelay + 'ms');
            
            setTimeout(function() {
                EGP_Popup.init();
            }, this.retryDelay);
        }
    };
    
    // Make EGP_Popup globally available
    window.EGP_Popup = EGP_Popup;
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Small delay to ensure all dependencies are loaded
        setTimeout(function() {
            EGP_Popup.init();
        }, 500);
    });
    
})(jQuery);
