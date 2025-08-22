/**
 * Elementor Editor Integration for Geo Rules
 * 
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    // Wait for Elementor to be ready
    $(window).on('elementor/init', function () {

        // Add Geo Rules panel to Elementor
        var GeoRulesPanel = elementor.modules.controls.BaseData.extend({
            onReady: function () {
                this.initGeoRules();
            },

            initGeoRules: function () {
                var self = this;

                // Create geo rules button
                var $button = $('<button>', {
                    type: 'button',
                    class: 'elementor-button elementor-button-default',
                    text: 'Configure Geo Rules'
                });

                $button.on('click', function () {
                    self.openGeoRulesModal();
                });

                // Add to Elementor panel
                this.$el.append($button);
            },

            openGeoRulesModal: function () {
                // Simple modal for now - can be enhanced later
                var modal = $('<div>', {
                    class: 'egp-modal-overlay',
                    html: '<div class="egp-modal"><div class="egp-modal-header"><h3>Geo Rules</h3><span class="egp-modal-close">&times;</span></div><div class="egp-modal-body">Geo Rules configuration coming soon...</div></div>'
                });

                modal.on('click', '.egp-modal-close', function () {
                    modal.remove();
                });

                $('body').append(modal);
            }
        });

        // Register the control
        elementor.hooks.addAction('panel/open_editor/widget', function (panel, model, view) {
            // Add geo rules to widget settings
            if (egpEditor && egpEditor.isPro) {
                panel.content.currentView.collection.add({
                    name: 'geo_rules',
                    label: 'Geo Rules',
                    type: 'geo_rules',
                    default: '',
                    control: GeoRulesPanel
                });
            }
        });

        // Add geo rules to page settings
        elementor.hooks.addAction('panel/open_editor/page', function (panel, model, view) {
            panel.content.currentView.collection.add({
                name: 'geo_rules',
                label: 'Geo Rules',
                type: 'geo_rules',
                default: '',
                control: GeoRulesPanel
            });
        });

    });

    // Add basic styles
    var styles = `
        .egp-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .egp-modal {
            background: white;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow: hidden;
        }
        
        .egp-modal-header {
            padding: 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .egp-modal-header h3 {
            margin: 0;
        }
        
        .egp-modal-close {
            cursor: pointer;
            font-size: 24px;
            line-height: 1;
        }
        
        .egp-modal-body {
            padding: 20px;
        }
    `;

    var styleSheet = document.createElement('style');
    styleSheet.textContent = styles;
    document.head.appendChild(styleSheet);

})(jQuery);
