/**
 * @module package/quiqqer/shipping/bin/backend/controls/settings/RuleFields
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/shipping/bin/backend/controls/settings/RuleFields', [

    'qui/QUI',
    'qui/controls/Control',
    'Locale',
    'Ajax',

    'css!package/quiqqer/shipping/bin/backend/controls/settings/RuleFields.css'

], function(QUI, QUIControl, QUILocale, QUIAjax) {
    'use strict';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/shipping/bin/backend/controls/settings/RuleFields',

        Binds: [
            '$onImport',
            '$updateInput'
        ],

        initialize: function(options) {
            this.parent(options);

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function() {
            this.$Input = this.getElm();
            this.$Elm = new Element('div', {
                'class': 'quiqqer-shipping-settings-ruleFields field-container-field'
            }).wraps(this.$Input);

            new Element('span', {
                html: '<span class="fa fa-spinner fa-spin"></span>'
            }).inject(this.$Elm);

            var self = this,
                Container = this.$Elm;

            this.$Elm.getParent('.field-container').getElement('.field-container-item').addEvent(
                'click',
                function(event) {
                    event.stop();
                }
            );

            QUIAjax.get('package_quiqqer_shipping_ajax_backend_rules_settings_getUnitFields', function(unitFields) {
                Container.getChildren().forEach(function(Node) {
                    if (Node.nodeName !== 'INPUT') {
                        Node.destroy();
                    }
                });

                for (var i = 0, len = unitFields.length; i < len; i++) {
                    new Element('label', {
                        'class': 'quiqqer-shipping-settings-ruleFields-entry',
                        html: '<input type="checkbox" value="' + unitFields[i].id + '" />' + unitFields[i].title,
                        events: {
                            change: self.$updateInput
                        }
                    }).inject(Container);
                }

                // check active fields
                var value = self.$Input.value;

                if (value === '') {
                    return;
                }

                value = value.split(',');

                for (i = 0, len = value.length; i < len; i++) {
                    Container.getElements('[value="' + value[i] + '"]').set('checked', true);
                }
            }, {
                'package': 'quiqqer/shipping'
            });
        },

        /**
         * Refresh the input value
         */
        $updateInput: function() {
            var checkboxes = this.getElm().getElements('[type="checkbox"]');

            checkboxes = checkboxes.filter(function(Node) {
                return Node.checked;
            });

            checkboxes = checkboxes.map(function(Node) {
                return Node.value;
            });

            this.$Input.value = checkboxes.join(',');
        }
    });

});