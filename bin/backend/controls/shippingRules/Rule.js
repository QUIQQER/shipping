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
    'package/quiqqer/shipping/bin/backend/ShippingRules',
    'qui/utils/Form',
    'Locale',
    'Mustache',

    'text!package/quiqqer/shipping/bin/backend/controls/shippingRules/Rule.html'

], function (QUI, QUIControl, Translator, TranslateUpdater, InputMultiLang,
             ShippingRules, FormUtils, QUILocale, Mustache, template
) {
    "use strict";

    var lg = 'quiqqer/shipping';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/shipping/bin/backend/controls/shippingRules/CreateRule',

        Binds: [
            '$onInject'
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

            QUI.parse(this.getElm()).then(function () {
                // locale for title and working title
                self.$DataTitle        = new InputMultiLang().replaces(self.$Elm.getElement('.shipping-title'));
                self.$DataWorkingTitle = new InputMultiLang().replaces(self.$Elm.getElement('.shipping-workingTitle'));

                require([
                    'package/quiqqer/shipping/bin/backend/ShippingRules',
                    'qui/utils/Form'
                ], function (ShippingRules, FormUtils) {
                    ShippingRules.getRule(self.getAttribute('ruleId')).then(function (rule) {
                        self.$DataTitle.setData(rule.title);
                        self.$DataWorkingTitle.setData(rule.workingTitle);

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
        submit: function () {
            if (!this.$DataTitle || !this.$DataWorkingTitle) {
                return Promise.reject('Missing DOMNode Elements');
            }

            var formData = FormUtils.getFormData(this.getElm().getElement('form'));

            formData.title        = this.$DataTitle.getData();
            formData.workingTitle = this.$DataWorkingTitle.getData();

            return ShippingRules.create(formData);
        }
    });
});
