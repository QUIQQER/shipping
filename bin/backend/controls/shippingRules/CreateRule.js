/**
 * @module package/quiqqer/shipping/bin/backend/controls/shippingRules/CreateRule
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/shipping/bin/backend/controls/shippingRules/CreateRule', [

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

    'text!package/quiqqer/shipping/bin/backend/controls/shippingRules/CreateRule.html',
    'text!package/quiqqer/shipping/bin/backend/controls/shippingRules/RuleUnit.html',
    'css!package/quiqqer/shipping/bin/backend/controls/shippingRules/Rule.css'

], function(QUI, QUIControl, Translator, TranslateUpdater, InputMultiLang, Fields,
    ShippingRules, FormUtils, QUILocale, Mustache, template, templateUnit
) {
    'use strict';

    const lg = 'quiqqer/shipping';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/shipping/bin/backend/controls/shippingRules/CreateRule',

        Binds: [
            '$onInject'
        ],

        initialize: function(options) {
            this.parent(options);

            this.$DataTitle = null;
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
        create: function() {
            this.$Elm = this.parent();
            this.$Elm.addClass('quiqqer-shipping-rule-create');

            this.$Elm.set('html', Mustache.render(template, {
                generalHeader: QUILocale.get(lg, 'shipping.edit.template.general'),
                title: QUILocale.get(lg, 'shipping.edit.template.title'),
                workingTitle: QUILocale.get('quiqqer/system', 'workingtitle'),
                calculationPriority: QUILocale.get(lg, 'shipping.edit.template.calculationPriority'),
                discountTitle: QUILocale.get(lg, 'shipping.edit.template.discount'),
                discountDescription: QUILocale.get(lg, 'shipping.edit.template.discount.description'),
                discountAbsolute: QUILocale.get(lg, 'shipping.edit.template.discount.absolute'),
                discountPercentage: QUILocale.get(lg, 'shipping.edit.template.discount.percentage'),
                discountPercentageOrder: QUILocale.get(lg, 'shipping.edit.template.discount.percentageOrder'),
                statusTitle: QUILocale.get(lg, 'shipping.edit.template.status'),
                statusDescription: QUILocale.get(lg, 'shipping.edit.template.status.description'),
                noRulesTitle: QUILocale.get(lg, 'shipping.edit.template.noRules'),
                noRulesText: QUILocale.get(lg, 'shipping.edit.template.noRules.text'),
                unitTitle: QUILocale.get(lg, 'shipping.edit.template.unit'),
                unitHeader: QUILocale.get(lg, 'shipping.edit.template.unitTitle'),
                usageHeader: QUILocale.get(lg, 'shipping.edit.template.usage'),
                usageFrom: QUILocale.get(lg, 'shipping.edit.template.usage.from'),
                usageTo: QUILocale.get(lg, 'shipping.edit.template.usage.to'),
                usageAmountOf: QUILocale.get(lg, 'shipping.edit.template.shopping.amount.of'),
                usageAmountTo: QUILocale.get(lg, 'shipping.edit.template.shopping.amount.to'),
                usageValueOf: QUILocale.get(lg, 'shipping.edit.template.purchase.value.of'),
                usageValueTo: QUILocale.get(lg, 'shipping.edit.template.purchase.value.to'),
                usageAssignmentUser: QUILocale.get(lg, 'shipping.edit.template.assignment.user'),
                usageAssignmentProduct: QUILocale.get(lg, 'shipping.edit.template.assignment.product'),
                usageAssignmentCategory: QUILocale.get(lg, 'shipping.edit.template.assignment.category'),
                usageAssignmentArea: QUILocale.get(lg, 'shipping.edit.template.assignment.areas'),

                productHeader: QUILocale.get(lg, 'shipping.edit.template.assignment.product.header'),
                usageAssignmentProductOnly: QUILocale.get(lg, 'shipping.edit.template.assignment.product.only'),
                usageAssignmentProductOnlyText: QUILocale.get(
                    lg,
                    'shipping.edit.template.assignment.product.only.text'
                ),
                usageAssignmentProductOnlyDesc: QUILocale.get(lg, 'shipping.edit.template.assignment.product.only.desc')
            }));

            this.$Elm.getElement('form').addEvent('submit', (e) => {
                e.stop();
            });

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function() {
            const self = this,
                current = QUILocale.getCurrent();

            const getOptions = function(field) {
                const entries = field.options.entries,
                    result = [];

                for (const i in entries) {
                    if (!entries.hasOwnProperty(i)) {
                        continue;
                    }

                    result.push({
                        key: i,
                        title: entries[i].title[current]
                    });
                }

                return result;
            };

            ShippingRules.getShippingRuleUnitFields().then(function(unitFields) {
                let i, len, html, field;

                const Table = self.getElm().getElement('.unit-table'),
                    Tbody = Table.getElement('tbody');

                Tbody.set('html', '');

                for (i = 0, len = unitFields.length; i < len; i++) {
                    field = unitFields[i];
                    html = Mustache.render(templateUnit, {
                        andText: QUILocale.get(lg, 'shipping.edit.template.and'),
                        options: getOptions(field),
                        id: field.id,
                        title: field.title
                    });

                    new Element('tr', {
                        html: '<td>' + html + '</td>'
                    }).inject(Tbody);
                }

                Tbody.getElements('select').set('disabled', false);
                Tbody.getElements('input').set('disabled', false);
            }).then(function() {
                return QUI.parse(self.getElm());
            }).then(function() {
                // locale for title and working title
                self.$DataTitle = new InputMultiLang().replaces(self.$Elm.getElement('.shipping-title'));
                self.$DataWorkingTitle = new InputMultiLang().replaces(self.$Elm.getElement('.shipping-workingTitle'));

                this.fireEvent('load', [this]);
            }.bind(this));
        },

        /**
         * create the new shipping rule
         *
         * @return {Promise}
         */
        submit: function() {
            if (!this.$DataTitle || !this.$DataWorkingTitle) {
                return Promise.reject('Missing DOMNode Elements');
            }

            const formData = FormUtils.getFormData(this.getElm().getElement('form'));

            formData.title = this.$DataTitle.getData();
            formData.workingTitle = this.$DataWorkingTitle.getData();

            let i, len, Unit, Term, Term2, Value, Value2, Label;

            const unitData = [];
            const UnitRows = this.getElm().getElements('.unit-table td');

            for (i = 0, len = UnitRows.length; i < len; i++) {
                Unit = UnitRows[i].getElement('[name="unit"]');
                Term = UnitRows[i].getElement('[name="term"]');
                Value = UnitRows[i].getElement('[name="value"]');
                Label = UnitRows[i].getElement('label');

                Term2 = UnitRows[i].getElement('[name="term"]');
                Value2 = UnitRows[i].getElement('[name="value"]');

                unitData.push({
                    id: parseInt(Label.get('data-id')),
                    value: Value.value,
                    value2: Value2.value,
                    term: Term.value,
                    term2: Term2.value,
                    unit: Unit.value
                });
            }

            formData.unit_terms = unitData;

            return ShippingRules.create(formData);
        }
    });
});
