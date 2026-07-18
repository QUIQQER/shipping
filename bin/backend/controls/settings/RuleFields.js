/**
 * @module package/quiqqer/shipping/bin/backend/controls/settings/RuleFields
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/shipping/bin/backend/controls/settings/RuleFields', [

    'qui/controls/Control',
    'Ajax',

    'css!package/quiqqer/shipping/bin/backend/controls/settings/RuleFields.css'

], function(QUIControl, QUIAjax) {
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
            this.$Elm = document.createElement('div');
            this.$Elm.className = 'quiqqer-shipping-settings-ruleFields field-container-field';
            this.$Input.parentNode.insertBefore(this.$Elm, this.$Input);
            this.$Elm.appendChild(this.$Input);

            var Spinner = document.createElement('span'),
                SpinnerIcon = document.createElement('span');

            SpinnerIcon.className = 'fa fa-spinner fa-spin';
            SpinnerIcon.setAttribute('aria-hidden', 'true');
            Spinner.appendChild(SpinnerIcon);
            this.$Elm.appendChild(Spinner);

            var self = this,
                Container = this.$Elm;

            var FieldContainer = this.$Elm.closest('.field-container'),
                FieldContainerItem = FieldContainer && FieldContainer.querySelector('.field-container-item');

            if (FieldContainerItem) {
                FieldContainerItem.addEventListener('click', function(event) {
                    event.stopPropagation();
                });
            }

            QUIAjax.get('package_quiqqer_shipping_ajax_backend_rules_settings_getUnitFields', function(unitFields) {
                Array.from(Container.children).forEach(function(Node) {
                    if (Node.nodeName !== 'INPUT') {
                        Node.remove();
                    }
                });

                for (var i = 0, len = unitFields.length; i < len; i++) {
                    var Label = document.createElement('label'),
                        Checkbox = document.createElement('input');

                    Label.className = 'quiqqer-shipping-settings-ruleFields-entry';
                    Checkbox.type = 'checkbox';
                    Checkbox.value = unitFields[i].id;
                    Checkbox.dataset.name = 'rule-field';
                    Checkbox.addEventListener('change', self.$updateInput);
                    Label.appendChild(Checkbox);
                    Label.appendChild(document.createTextNode(unitFields[i].title));
                    Container.appendChild(Label);
                }

                // check active fields
                var value = self.$Input.value;

                if (value === '') {
                    return;
                }

                value = new Set(value.split(','));

                Container.querySelectorAll('[data-name="rule-field"]').forEach(function(Checkbox) {
                    Checkbox.checked = value.has(Checkbox.value);
                });
            }, {
                'package': 'quiqqer/shipping'
            });
        },

        /**
         * Refresh the input value
         */
        $updateInput: function() {
            var checkboxes = Array.from(this.$Elm.querySelectorAll('[data-name="rule-field"]'));

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
