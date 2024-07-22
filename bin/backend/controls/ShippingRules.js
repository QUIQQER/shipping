/**
 * @module package/quiqqer/shipping/bin/backend/controls/ShippingRules
 * @author www.pcsg.de (Henning Leutz)
 *
 * Shipping Panel
 */
define('package/quiqqer/shipping/bin/backend/controls/ShippingRules', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'package/quiqqer/shipping/bin/backend/controls/shippingRules/ShippingRuleList',
    'Locale'

], function(QUI, QUIPanel, ShippingRuleList, QUILocale) {
    'use strict';

    var lg = 'quiqqer/shipping';

    return new Class({

        Extends: QUIPanel,
        Type: 'package/quiqqer/shipping/bin/backend/controls/ShippingRules',

        Binds: [
            '$onCreate',
            '$onInject'
        ],

        initialize: function(options) {
            this.parent(options);

            this.setAttributes({
                icon: 'fa fa-truck',
                title: QUILocale.get(lg, 'menu.erp.shipping.rules.title')
            });

            this.$List = null;

            this.addEvents({
                onCreate: this.$onCreate,
                onInject: this.$onInject,
                onShow: this.$onInject
            });
        },

        /**
         * event: on create
         */
        $onCreate: function() {
            var self = this;

            this.$List = new ShippingRuleList({
                events: {
                    onRefreshBegin: function() {
                        self.Loader.show();
                    },

                    onRefreshEnd: function() {
                        self.Loader.hide();
                    }
                }
            }).inject(this.getContent());
        },

        /**
         * event: on resize
         */
        $onInject: function() {
            (function() {
                this.$List.resize();
            }).delay(200, this);
        }
    });
});
