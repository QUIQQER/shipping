/**
 * @module
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/shipping/bin/backend/classes/ShippingRules', [

    'qui/QUI',
    'qui/classes/DOM',
    'Ajax'

], function (QUI, QUIDOM, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/quiqqer/shipping/bin/backend/classes/ShippingRules',

        initialize: function (options) {
            this.parent(options);

            this.$list = null;
        },

        getList: function () {
            if (this.$list) {
                return window.Promise.resolve(this.$list);
            }

            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_shipping_ajax_backend_rules_getList', function (result) {
                    self.$list = result;
                    resolve(self.$list);
                }, {
                    'package': 'quiqqer/shipping',
                    onError  : reject
                });
            });
        }
    });
});
