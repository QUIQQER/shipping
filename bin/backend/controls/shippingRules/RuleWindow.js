/**
 * @module package/quiqqer/shipping/bin/backend/controls/shippingRules/CreateRuleWindow
 * @author www.pcsg.de (Henning Leutz
 */
define('package/quiqqer/shipping/bin/backend/controls/shippingRules/RuleWindow', [

    'qui/QUI',
    'qui/controls/windows/Confirm',
    'package/quiqqer/shipping/bin/backend/controls/shippingRules/Rule',
    'Locale'

], function (QUI, QUIConfirm, Rule, QUILocale) {
    "use strict";

    return new Class({

        Extends: QUIConfirm,
        Type   : 'package/quiqqer/shipping/bin/backend/controls/shippingRules/RuleWindow',

        Binds: [
            '$onOpen',
            '$onSubmit'
        ],

        options: {
            maxHeight: 600,
            maxWidth : 600,
            autoclose: false,
            ruleId   : false
        },

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                title: QUILocale.get('quiqqer/shipping', 'window.shipping.rules.title'),
                icon : 'fa fa-truck'
            });

            this.$Rule = null;

            this.addEvents({
                onOpen  : this.$onOpen,
                onSubmit: this.$onSubmit
            });
        },

        /**
         * event: on inject
         */
        $onOpen: function () {
            var self = this;

            this.Loader.show();
            this.getContent().set('html', '');

            this.$Rule = new Rule({
                ruleId: this.getAttribute('ruleId'),
                events: {
                    onLoad: function () {
                        self.Loader.hide();
                    }
                }
            }).inject(this.getContent());
        },

        /**
         * event: on submit
         */
        $onSubmit: function () {
            var self = this;

            this.Loader.hide();
            this.$Rule.update().then(function () {
                self.Loader.hide();
            });
        }
    });
});
