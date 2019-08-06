/**
 * @module package/quiqqer/shipping/bin/backend/controls/shippingRules/Rule
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/shipping/bin/backend/controls/shippingRules/Rule', [

    'qui/QUI',
    'qui/controls/Control',
    'utils/Controls',
    'package/quiqqer/translator/bin/Translator',
    'package/quiqqer/translator/bin/controls/Update',
    'controls/lang/InputMultiLang',
    'package/quiqqer/products/bin/Fields',
    'package/quiqqer/shipping/bin/backend/ShippingRules',
    'qui/utils/Form',
    'qui/utils/Elements',
    'Locale',
    'Mustache',

    'text!package/quiqqer/shipping/bin/backend/controls/shippingRules/Rule.html'

], function (QUI, QUIControl, ControlUtils, Translator, TranslateUpdater, InputMultiLang, Fields,
             ShippingRules, FormUtils, ElementsUtils, QUILocale, Mustache, template
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
            this.$UserGroups       = null;
            this.$Articles         = null;

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

            this.$Elm.setStyles({
                overflow: 'hidden',
                opacity : 0
            });

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

                usageHeader           : QUILocale.get(lg, 'shipping.edit.template.usage'),
                usageFrom             : QUILocale.get(lg, 'shipping.edit.template.usage.from'),
                usageTo               : QUILocale.get(lg, 'shipping.edit.template.usage.to'),
                usageAmountOf         : QUILocale.get(lg, 'shipping.edit.template.shopping.amount.of'),
                usageAmountTo         : QUILocale.get(lg, 'shipping.edit.template.shopping.amount.to'),
                usageValueOf          : QUILocale.get(lg, 'shipping.edit.template.purchase.value.of'),
                usageValueTo          : QUILocale.get(lg, 'shipping.edit.template.purchase.value.to'),
                usageAssignmentUser   : QUILocale.get(lg, 'shipping.edit.template.assignment.user'),
                usageAssignmentProduct: QUILocale.get(lg, 'shipping.edit.template.assignment.product'),

                productHeader                 : QUILocale.get(lg, 'shipping.edit.template.assignment.product.header'),
                usageAssignmentProductOnly    : QUILocale.get(lg, 'shipping.edit.template.assignment.product.only'),
                usageAssignmentProductOnlyText: QUILocale.get(lg, 'shipping.edit.template.assignment.product.only.text'),
                usageAssignmentProductOnlyDesc: QUILocale.get(lg, 'shipping.edit.template.assignment.product.only.desc')
            }));

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this;

            Fields.getChild(Fields.FIELD_WEIGHT).then(function (Unit) {
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
                return ControlUtils.parse(self.getElm());
            }).then(function () {
                ElementsUtils.simulateEvent(
                    self.getElm().getElement('.usage-table thead .data-table-toggle'),
                    'click'
                );

                ElementsUtils.simulateEvent(
                    self.getElm().getElement('.product-table thead .data-table-toggle'),
                    'click'
                );

                ElementsUtils.simulateEvent(
                    self.getElm().getElement('.payment-table thead .data-table-toggle'),
                    'click'
                );

                return new Promise(function (resolve) {
                    resolve.delay(500);
                });
            }).then(function () {
                return QUI.parse(self.getElm());
            }).then(function () {
                // locale for title and working title
                self.$DataTitle        = new InputMultiLang().replaces(self.$Elm.getElement('.shipping-title'));
                self.$DataWorkingTitle = new InputMultiLang().replaces(self.$Elm.getElement('.shipping-workingTitle'));

                self.$UserGroups = QUI.Controls.getById(
                    self.$Elm
                        .getElement('[name="user_groups"]')
                        .getParent('.qui-elements-select')
                        .get('data-quiid')
                );

                self.$Articles = QUI.Controls.getById(
                    self.$Elm
                        .getElement('[name="articles"]')
                        .getParent('.qui-elements-select')
                        .get('data-quiid')
                );

                return new Promise(function (resolve) {
                    require([
                        'package/quiqqer/shipping/bin/backend/ShippingRules',
                        'qui/controls/buttons/Switch',
                        'qui/utils/Form'
                    ], function (ShippingRules, QUISwitch, FormUtils) {
                        ShippingRules.getRule(self.getAttribute('ruleId')).then(function (rule) {
                            self.$DataTitle.setData(rule.title);
                            self.$DataWorkingTitle.setData(rule.workingTitle);

                            self.$UserGroups.importValue(rule.user_groups);
                            self.$Articles.importValue(rule.articles);

                            new QUISwitch({
                                status: parseInt(rule.active),
                                name  : 'status'
                            }).inject(self.getElm().getElement('.field-shipping-rules'));

                            FormUtils.setDataToForm(rule, self.getElm().getElement('form'));

                            resolve();
                        });
                    });
                });
            }).then(function () {
                self.getElm().setStyle('overflow', null);

                moofx(self.getElm()).animate({
                    opacity: 1
                }, {
                    duration: 200,
                    callback: function () {
                        self.getElm().setStyle('opacity', null);
                        self.fireEvent('load', [self]);
                    }
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
