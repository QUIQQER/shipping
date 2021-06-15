/**
 * @module package/quiqqer/shipping/bin/backend/controls/products/fields/ShippingTimePeriod
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/shipping/bin/backend/controls/products/fields/ShippingTimePeriod', [

    'qui/QUI',
    'package/quiqqer/products/bin/controls/fields/types/TimePeriod',
    'Locale',

    'css!package/quiqqer/shipping/bin/backend/controls/products/fields/ShippingTimePeriod.css'

], function (QUI, TimePeriod, QUILocale) {
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

        options: {
            selectOptions: [
                'timeperiod', 'unavailable', 'immediately_available',
                'on_request', 'available_soon', 'custom_text'
            ],

            show_default_option: true
        },

        initialize: function (options) {
            this.parent(options);

            this.$OptionsSelect       = null;
            this.$CustomTextContainer = null;
            this.$CustomTextInput     = null;
            this.$CustomText          = null;

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

            // Options select
            var OptionsContainer = new Element('div', {
                'class': 'quiqqer-shipping-fields-shippingtimeperiod'
            }).inject(this.$Content, 'top');

            this.$OptionsSelect = new Element('select', {
                name  : 'option',
                events: {
                    change: this.$onOptionSelectChange
                }
            }).inject(OptionsContainer);

            var options  = this.getAttribute('selectOptions'),
                lgPrefix = 'controls.products.fields.ShippingTimePeriod.';

            if (this.getAttribute('show_default_option')) {
                options.unshift('use_default');
            }

            for (var i = 0, len = options.length; i < len; i++) {
                var option = options[i];

                new Element('option', {
                    value: option,
                    html : QUILocale.get(lg, lgPrefix + 'option.' + option)
                }).inject(this.$OptionsSelect);
            }

            // Custom text input
            this.$CustomTextContainer = new Element('div', {
                'class': 'quiqqer-shipping-fields-shippingtimeperiod-customtext quiqqer-shipping-fields-shippingtimeperiod__hidden',
            }).inject(
                Elm.getParent().getElement('.quiqqer-products-fields-types-timeperiod')
            );

            var CustomTextInput = new Element('input', {
                type      : 'text',
                'data-qui': 'package/quiqqer/products/bin/controls/fields/types/InputMultiLang'
            }).inject(this.$CustomTextContainer);

            QUI.parse(this.$CustomTextContainer).then(function () {
                self.$CustomText = QUI.Controls.getById(
                    CustomTextInput.get('data-quiid')
                );

                self.$CustomText.getElm().getElements('input').addEvent('change', self.$onOptionSelectChange);

                (function () {
                    if (!Elm.value) {
                        self.$OptionsSelect.value = 'timeperiod';
                        return;
                    }

                    Value = JSON.decode(Elm.value);

                    if (Value.option === 'custom_text') {
                        self.$CustomText.setData(Value.text);
                    }

                    self.$OptionsSelect.value = Value.option;
                    self.$onOptionSelectChange();
                }).delay(200);
            });
        },

        /**
         * Executed if an option is selected
         */
        $onOptionSelectChange: function () {
            var option = this.$OptionsSelect.value;

            if (option === 'timeperiod') {
                this.$UnitSelect.getParent().removeClass('quiqqer-shipping-fields-shippingtimeperiod__hidden');
                this.$FromInput.getParent().removeClass('quiqqer-shipping-fields-shippingtimeperiod__hidden');
                this.$ToInput.getParent().removeClass('quiqqer-shipping-fields-shippingtimeperiod__hidden');
            } else {
                this.$UnitSelect.getParent().addClass('quiqqer-shipping-fields-shippingtimeperiod__hidden');
                this.$FromInput.getParent().addClass('quiqqer-shipping-fields-shippingtimeperiod__hidden');
                this.$ToInput.getParent().addClass('quiqqer-shipping-fields-shippingtimeperiod__hidden');
            }

            if (option === 'custom_text') {
                this.$CustomTextContainer.removeClass('quiqqer-shipping-fields-shippingtimeperiod__hidden');
            } else {
                this.$CustomTextContainer.addClass('quiqqer-shipping-fields-shippingtimeperiod__hidden');
            }

            this.$setValue();
        },

        /**
         * Set field value to input
         */
        $setValue: function () {
            var customText = this.$CustomText.getValue().trim();

            if (customText !== '') {
                customText = JSON.decode(customText);
            }

            this.getElm().value = JSON.encode({
                from  : this.$FromInput.value.trim(),
                to    : this.$ToInput.value.trim(),
                unit  : this.$UnitSelect.value,
                option: this.$OptionsSelect.value,
                text  : customText
            });
        },

        /**
         * Return the current value
         *
         * @returns {Object}
         */
        getValue: function () {
            var customText = this.$CustomText.getValue().trim();

            if (customText !== '') {
                customText = JSON.decode(customText);
            }

            return {
                from  : this.$FromInput.value.trim(),
                to    : this.$ToInput.value.trim(),
                unit  : this.$UnitSelect.value,
                option: this.$OptionsSelect.value,
                text  : customText
            };
        }
    });
});
