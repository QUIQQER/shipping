/**
 * @module package/quiqqer/shipping/bin/backend/controls/shippingRules/Rule
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/shipping/bin/backend/controls/shippingRules/Rule', [

    'qui/QUI',
    'qui/controls/Control',
    'package/quiqqer/translator/bin/Translator',
    'package/quiqqer/translator/bin/controls/Update',
    'controls/lang/InputMultiLang',
    'package/quiqqer/products/bin/Fields',
    'package/quiqqer/shipping/bin/backend/ShippingRules',
    'qui/utils/Form',
    'Locale',
    'Mustache',

    'text!package/quiqqer/shipping/bin/backend/controls/shippingRules/Rule.html'

], function (QUI, QUIControl, Translator, TranslateUpdater, InputMultiLang, Fields,
             ShippingRules, FormUtils, QUILocale, Mustache, template
) {
    "use strict";

    var lg = 'quiqqer/shipping';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/shipping/bin/backend/controls/shippingRules/CreateRule',

        Binds: [
            '$onInject',
            'update'
        ],

        options: {
            ruleId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$DataTitle        = null;
            this.$DataWorkingTitle = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Create the DOMNode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();

            this.$Elm.set('html', Mustache.render(template, {
                generalHeader      : QUILocale.get(lg, 'shipping.edit.template.general'),
                title              : QUILocale.get(lg, 'shipping.edit.template.title'),
                workingTitle       : QUILocale.get('quiqqer/system', 'workingtitle'),
                calculationPriority: QUILocale.get(lg, 'shipping.edit.template.calculationPriority'),
                discountTitle      : QUILocale.get(lg, 'shipping.edit.template.discount'),
                discountAbsolute   : QUILocale.get(lg, 'shipping.edit.template.discount.absolute'),
                discountPercentage : QUILocale.get(lg, 'shipping.edit.template.discount.percentage'),
                statusTitle        : QUILocale.get(lg, 'shipping.edit.template.status'),
                unitTitle          : QUILocale.get(lg, 'shipping.edit.template.unit'),

                usageHeader  : QUILocale.get(lg, 'shipping.edit.template.usage'),
                usageFrom    : QUILocale.get(lg, 'shipping.edit.template.usage.from'),
                usageTo      : QUILocale.get(lg, 'shipping.edit.template.usage.to'),
                usageAmountOf: QUILocale.get(lg, 'shipping.edit.template.shopping.amount.of'),
                usageAmountTo: QUILocale.get(lg, 'shipping.edit.template.shopping.amount.to'),
                usageValueOf : QUILocale.get(lg, 'shipping.edit.template.purchase.value.of'),
                usageValueTo : QUILocale.get(lg, 'shipping.edit.template.purchase.value.to')
            }));

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this;

            Fields.getChild(Fields.FIELD_UNIT).then(function (Unit) {
                var title;

                var entries = Unit.options.entries,
                    Select  = self.getElm().getElement('.field-shipping-unit select'),
                    Input   = self.getElm().getElement('.field-shipping-unit input'),
                    current = QUILocale.getCurrent();

                new Element('option', {
                    value: '',
                    html : '---'
                }).inject(Select);

                for (var unit in entries) {
                    if (!entries.hasOwnProperty(unit)) {
                        continue;
                    }

                    title = unit;

                    if (typeof entries[unit].title[current] !== 'undefined') {
                        title = entries[unit].title[current];
                    }

                    new Element('option', {
                        value: unit,
                        html : title
                    }).inject(Select);
                }

                Select.set('disabled', false);
                Input.set('disabled', false);
            }).then(function () {
                return QUI.parse(self.getElm());
            }).then(function () {
                // locale for title and working title
                self.$DataTitle        = new InputMultiLang().replaces(self.$Elm.getElement('.shipping-title'));
                self.$DataWorkingTitle = new InputMultiLang().replaces(self.$Elm.getElement('.shipping-workingTitle'));

                require([
                    'package/quiqqer/shipping/bin/backend/ShippingRules',
                    'qui/controls/buttons/Switch',
                    'qui/utils/Form'
                ], function (ShippingRules, QUISwitch, FormUtils) {
                    ShippingRules.getRule(self.getAttribute('ruleId')).then(function (rule) {
                        self.$DataTitle.setData(rule.title);
                        self.$DataWorkingTitle.setData(rule.workingTitle);

                        new QUISwitch({
                            status: parseInt(rule.active),
                            name  : 'status'
                        }).inject(self.getElm().getElement('.field-shipping-rules'));

                        FormUtils.setDataToForm(rule, self.getElm().getElement('form'));

                        self.fireEvent('load', [self]);
                    });
                });
            });
        },

        /**
         * create the new shipping rule
         *
         * @return {Promise}
         */
        update: function () {
            if (!this.$DataTitle || !this.$DataWorkingTitle) {
                return Promise.reject('Missing DOMNode Elements');
            }

            var formData = FormUtils.getFormData(this.getElm().getElement('form'));

            formData.title        = this.$DataTitle.getData();
            formData.workingTitle = this.$DataWorkingTitle.getData();
            formData.active       = parseInt(formData.status);

            return ShippingRules.update(this.getAttribute('ruleId'), formData);
        }
    });
});
