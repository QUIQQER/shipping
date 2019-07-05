/**
 * @module package/quiqqer/shipping/bin/backend/controls/shippingRules/ShippingRuleList
 * @author www.pcsg.de (Henning Leutz
 */
define('package/quiqqer/shipping/bin/backend/controls/shippingRules/ShippingRuleListWindow', [

    'qui/QUI',
    'qui/controls/windows/Confirm',
    'package/quiqqer/shipping/bin/backend/controls/shippingRules/ShippingRuleList',
    'Locale'

], function (QUI, QUIConfirm, List, QUILocale) {
    "use strict";

    return new Class({

        Extends: QUIConfirm,
        Type   : 'package/quiqqer/shipping/bin/backend/controls/shippingRules/ShippingRuleListWindow',

        Binds: [
            '$onOpen',
            '$onSubmit'
        ],

        options: {
            maxHeight: 600,
            maxWidth : 600,
            autoclose: false
        },

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                title: QUILocale.get('quiqqer/shipping', 'window.shipping.rules.title'),
                icon : 'fa fa-truck'
            });

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * event: on inject
         */
        $onOpen: function () {
            var self = this;

            this.Loader.show();
            this.getContent().set('html', '');

            this.$List = new List({
                events: {
                    onRefresh: function () {
                        self.Loader.hide();
                    },

                    onOpenCreateRuleWindow: function () {
                        self.close();
                    },

                    onCloseCreateRuleWindow: function () {
                        self.open();
                    }
                }
            }).inject(this.getContent());


            this.$List.resize();
        },

        /**
         * event: on submit
         */
        $onSubmit: function () {
            var selected = this.$List.getSelected();

        }
    });
});
