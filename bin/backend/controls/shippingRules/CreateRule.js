/**
 * @module
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/shipping/bin/backend/controls/shippingRules/CreateRule', [

    'qui/QUI',
    'qui/controls/Control',
    'Locale',
    'Mustache',

    'text!package/quiqqer/shipping/bin/backend/controls/shippingRules/CreateRule.html'

], function (QUI, QUIControl, QUILocale, Mustache, template) {
    "use strict";

    var lg = 'quiqqer/shipping';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/shipping/bin/backend/controls/shippingRules/CreateRule',

        Binds: [
            '$onInject'
        ],

        initialize: function (options) {
            this.parent(options);

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
            this.fireEvent('load', [this]);
        },

        /**
         * create the new shipping rule
         *
         * @return {Promise}
         */
        submit: function () {
            return new Promise(function (resolve) {

                resolve();
            });
        }
    });
});
