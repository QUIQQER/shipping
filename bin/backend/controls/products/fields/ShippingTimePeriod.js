/**
 * @module package/quiqqer/shipping/bin/backend/controls/products/fields/ShippingTimePeriod
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/shipping/bin/backend/controls/products/fields/ShippingTimePeriod', [

    'package/quiqqer/products/bin/controls/fields/types/TimePeriod',
    'Locale',

    'css!package/quiqqer/shipping/bin/backend/controls/products/fields/ShippingTimePeriod.css'

], function (TimePeriod, QUILocale) {
    "use strict";

    var lg = 'quiqqer/shipping';

    return new Class({
        Extends: TimePeriod,
        Type   : 'package/quiqqer/shipping/bin/backend/controls/products/fields/ShippingTimePeriod',

        Binds: [
            '$onImport',
            '$onOptionSelectChange',
            '$onSelectChange',
            '$setValue',
            'getValue'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$OptionsSelect = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * Event: onImport
         */
        $onImport: function () {
            var self  = this,
                Elm   = this.getElm(),
                Value = false;

            this.parent();

            var OptionsContainer = new Element('div', {
                'class': 'quiqqer-shipping-fields-shippingtimeperiod'
            }).inject(this.$Content, 'top');

            this.$OptionsSelect = new Element('select', {
                name  : 'option',
                events: {
                    change: this.$onOptionSelectChange
                }
            }).inject(OptionsContainer);

            var options  = ['timeperiod', 'unavailable', 'immediately_available'],
                lgPrefix = 'controls.products.fields.ShippingTimePeriod.';

            for (var i = 0, len = options.length; i < len; i++) {
                var option = options[i];

                new Element('option', {
                    value: option,
                    html : QUILocale.get(lg, lgPrefix + 'option.' + option)
                }).inject(this.$OptionsSelect);
            }

            (function () {
                if (!Elm.value) {
                    self.$OptionsSelect.value = 'timeperiod';
                    return;
                }

                Value = JSON.decode(Elm.value);

                self.$OptionsSelect.value = Value.option;
                self.$onOptionSelectChange();
            }).delay(200);
        },

        /**
         * Executed if an option is selected
         */
        $onOptionSelectChange: function () {
            var option = this.$OptionsSelect.value;

            if (option === 'timeperiod') {
                this.$UnitSelect.disabled = false;
                this.$FromInput.disabled  = false;
                this.$ToInput.disabled    = false;
            } else {
                this.$UnitSelect.disabled = true;
                this.$FromInput.disabled  = true;
                this.$ToInput.disabled    = true;
            }

            this.$setValue();
        },

        /**
         * Set field value to input
         */
        $setValue: function () {
            this.getElm().value = JSON.encode({
                from  : this.$FromInput.value.trim(),
                to    : this.$ToInput.value.trim(),
                unit  : this.$UnitSelect.value,
                option: this.$OptionsSelect.value
            });
        },

        /**
         * Return the current value
         *
         * @returns {Object}
         */
        getValue: function () {
            return {
                from  : this.$FromInput.value.trim(),
                to    : this.$ToInput.value.trim(),
                unit  : this.$UnitSelect.value,
                option: this.$OptionsSelect.value
            };
        }
    });
});
