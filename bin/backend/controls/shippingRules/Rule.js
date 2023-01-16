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

    'text!package/quiqqer/shipping/bin/backend/controls/shippingRules/Rule.html',
    'text!package/quiqqer/shipping/bin/backend/controls/shippingRules/RuleUnit.html',
    'css!package/quiqqer/shipping/bin/backend/controls/shippingRules/Rule.css'

], function (QUI, QUIControl, ControlUtils, Translator, TranslateUpdater, InputMultiLang, Fields,
             ShippingRules, FormUtils, ElementsUtils, QUILocale, Mustache,
             template, templateUnit
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

            this.$DataTitle = null;
            this.$DataWorkingTitle = null;
            this.$UserGroups = null;
            this.$Areas = null;
            this.$Articles = null;

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

            this.$Elm.addClass('quiqqer-shipping-rule-edit');

            this.$Elm.setStyles({
                overflow: 'hidden',
                opacity : 0
            });

            this.$Elm.set('html', Mustache.render(template, {
                generalHeader          : QUILocale.get(lg, 'shipping.edit.template.general'),
                title                  : QUILocale.get(lg, 'shipping.edit.template.title'),
                workingTitle           : QUILocale.get('quiqqer/system', 'workingtitle'),
                calculationPriority    : QUILocale.get(lg, 'shipping.edit.template.calculationPriority'),
                discountTitle          : QUILocale.get(lg, 'shipping.edit.template.discount'),
                discountDescription    : QUILocale.get(lg, 'shipping.edit.template.discount.description'),
                discountAbsolute       : QUILocale.get(lg, 'shipping.edit.template.discount.absolute'),
                discountPercentage     : QUILocale.get(lg, 'shipping.edit.template.discount.percentage'),
                discountPercentageOrder: QUILocale.get(lg, 'shipping.edit.template.discount.percentageOrder'),
                statusTitle            : QUILocale.get(lg, 'shipping.edit.template.status'),
                statusDescription      : QUILocale.get(lg, 'shipping.edit.template.status.description'),
                noRulesTitle           : QUILocale.get(lg, 'shipping.edit.template.noRules'),
                noRulesText            : QUILocale.get(lg, 'shipping.edit.template.noRules.text'),
                unitTitle              : QUILocale.get(lg, 'shipping.edit.template.unit'),
                unitHeader             : QUILocale.get(lg, 'shipping.edit.template.unitTitle'),
                usageHeader            : QUILocale.get(lg, 'shipping.edit.template.usage'),
                usageFrom              : QUILocale.get(lg, 'shipping.edit.template.usage.from'),
                usageTo                : QUILocale.get(lg, 'shipping.edit.template.usage.to'),
                usageAmountOf          : QUILocale.get(lg, 'shipping.edit.template.shopping.amount.of'),
                usageAmountTo          : QUILocale.get(lg, 'shipping.edit.template.shopping.amount.to'),
                usageValueOf           : QUILocale.get(lg, 'shipping.edit.template.purchase.value.of'),
                usageValueTo           : QUILocale.get(lg, 'shipping.edit.template.purchase.value.to'),
                usageAssignmentUser    : QUILocale.get(lg, 'shipping.edit.template.assignment.user'),
                usageAssignmentProduct : QUILocale.get(lg, 'shipping.edit.template.assignment.product'),
                usageAssignmentArea    : QUILocale.get(lg, 'shipping.edit.template.assignment.areas'),
                usageAssignmentCategory: QUILocale.get(lg, 'shipping.edit.template.assignment.category'),

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
            var self    = this,
                current = QUILocale.getCurrent();

            var getOptions = function (field) {
                var entries = field.options.entries,
                    result  = [];

                for (var i in entries) {
                    if (!entries.hasOwnProperty(i)) {
                        continue;
                    }

                    result.push({
                        key  : i,
                        title: entries[i].title[current]
                    });
                }

                return result;
            };

            ShippingRules.getShippingRuleUnitFields().then(function (unitFields) {
                var i, len, html, field;

                var Table = self.getElm().getElement('.unit-table'),
                    Tbody = Table.getElement('tbody');

                Tbody.set('html', '');

                // defining units
                for (i = 0, len = unitFields.length; i < len; i++) {
                    field = unitFields[i];
                    html = Mustache.render(templateUnit, {
                        andText: QUILocale.get(lg, 'shipping.edit.template.and'),
                        options: getOptions(field),
                        id     : field.id,
                        title  : field.title
                    });

                    new Element('tr', {
                        html: '<td>' + html + '</td>'
                    }).inject(Tbody);
                }

                Tbody.getElements('select').set('disabled', false);
                Tbody.getElements('input').set('disabled', false);
            }).then(function () {
                return ControlUtils.parse(self.getElm());
            }).then(function () {
                var CalcButton = self.getElm().getElement('[name="calc"]');
                var Discount = self.getElm().getElement('[name="discount"]');

                CalcButton.disabled = false;
                CalcButton.title = QUILocale.get('quiqqer/erp', 'control.window.price.brutto.title');

                CalcButton.addEvent('click', function () {
                    var Fa = CalcButton.getElement('.fa');

                    Fa.addClass('fa-spinner');
                    Fa.addClass('fa-spin');
                    Fa.removeClass('fa-calculator');

                    require([
                        'package/quiqqer/erp/bin/backend/controls/articles/windows/PriceBrutto'
                    ], function (PriceBruttoWindow) {
                        new PriceBruttoWindow({
                            events: {
                                onSubmit: function (Win, value) {
                                    Discount.value = value;
                                }
                            }
                        }).open();

                        Fa.removeClass('fa-spinner');
                        Fa.removeClass('fa-spin');
                        Fa.addClass('fa-calculator');
                    });
                });
            }).then(function () {
                ElementsUtils.simulateEvent(
                    self.getElm().getElement('.usage-table thead .data-table-toggle'),
                    'click'
                );

                ElementsUtils.simulateEvent(
                    self.getElm().getElement('.product-table thead .data-table-toggle'),
                    'click'
                );

                return new Promise(function (resolve) {
                    resolve.delay(500);
                });
            }).then(function () {
                return QUI.parse(self.getElm());
            }).then(function () {
                // locale for title and working title
                self.$DataTitle = new InputMultiLang().replaces(self.$Elm.getElement('.shipping-title'));
                self.$DataWorkingTitle = new InputMultiLang().replaces(self.$Elm.getElement('.shipping-workingTitle'));

                self.$PurchaseFrom = QUI.Controls.getById(
                    self.$Elm.getElement('[name="purchase_value_from"]').get('data-quiid')
                );

                self.$PurchaseUntil = QUI.Controls.getById(
                    self.$Elm.getElement('[name="purchase_value_until"]').get('data-quiid')
                );

                self.$UserGroups = QUI.Controls.getById(
                    self.$Elm
                        .getElement('[name="user_groups"]')
                        .getParent('.qui-elements-select')
                        .get('data-quiid')
                );

                self.$Areas = QUI.Controls.getById(
                    self.$Elm
                        .getElement('[name="areas"]')
                        .getParent('.qui-elements-select')
                        .get('data-quiid')
                );

                self.$Articles = QUI.Controls.getById(
                    self.$Elm
                        .getElement('[name="articles"]')
                        .getParent('.qui-elements-select')
                        .get('data-quiid')
                );

                self.$Categories = QUI.Controls.getById(
                    self.$Elm
                        .getElement('[name="categories"]')
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

                            self.$Areas.importValue(rule.areas);
                            self.$UserGroups.importValue(rule.user_groups);
                            self.$Articles.importValue(rule.articles);
                            self.$Categories.importValue(rule.categories);
                            
                            if (rule.purchase_value_from) {
                                self.$PurchaseFrom.setNetto(rule.purchase_value_from);
                            }

                            if (rule.purchase_value_until) {
                                self.$PurchaseUntil.setNetto(rule.purchase_value_until);
                            }

                            new QUISwitch({
                                status: parseInt(rule.active),
                                name  : 'status'
                            }).inject(self.getElm().getElement('.field-shipping-rules'));

                            FormUtils.setDataToForm(rule, self.getElm().getElement('form'));

                            // unit terms
                            var i, len, term, Row;
                            var terms = rule.unit_terms;
                            var UnitTable = self.getElm().getElement('.unit-table');

                            for (i = 0, len = terms.length; i < len; i++) {
                                term = terms[i];
                                Row = UnitTable.getElement('[data-id="' + term.id + '"]');

                                if (!Row) {
                                    continue;
                                }

                                Row.getElement('[name="unit"]').value = term.unit;
                                Row.getElement('[name="term"]').value = term.term;
                                Row.getElement('[name="value"]').value = term.value;

                                if (typeof term.unit2 !== 'undefined') {
                                    Row.getElement('[name="unit2"]').value = term.unit2;
                                }

                                if (typeof term.term2 !== 'undefined') {
                                    Row.getElement('[name="term2"]').value = term.term2;
                                }

                                if (typeof term.value2 !== 'undefined') {
                                    Row.getElement('[name="value2"]').value = term.value2;
                                }
                            }

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

            formData.title = this.$DataTitle.getData();
            formData.workingTitle = this.$DataWorkingTitle.getData();
            formData.active = parseInt(formData.status);

            var i, len, Unit, Term, Term2, Value, Value2, Label;

            var unitData = [];
            var UnitRows = this.getElm().getElements('.unit-table td');

            for (i = 0, len = UnitRows.length; i < len; i++) {
                Unit = UnitRows[i].getElement('[name="unit"]');
                Term = UnitRows[i].getElement('[name="term"]');
                Term2 = UnitRows[i].getElement('[name="term2"]');
                Value = UnitRows[i].getElement('[name="value"]');
                Value2 = UnitRows[i].getElement('[name="value2"]');
                Label = UnitRows[i].getElement('label');

                unitData.push({
                    id    : parseInt(Label.get('data-id')),
                    value : Value.value,
                    value2: Value2.value,
                    term  : Term.value,
                    term2 : Term2.value,
                    unit  : Unit.value
                });
            }

            formData.unit_terms = unitData;

            return ShippingRules.update(this.getAttribute('ruleId'), formData);
        }
    });
});
