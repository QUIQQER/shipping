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

        /**
         * Return
         * @return {Promise|*}
         */
        getList: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_shipping_ajax_backend_rules_getList', resolve, {
                    'package': 'quiqqer/shipping',
                    onError  : reject
                });
            });
        },

        /**
         * Create a new rule
         *
         * @param rules
         * @return {Promise}
         */
        create: function (rules) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_shipping_ajax_backend_rules_create', resolve, {
                    'package': 'quiqqer/shipping',
                    rules    : JSON.encode(rules),
                    onError  : reject
                });
            });
        },

        /**
         * Return the wanted rules
         *
         * @param ruleIds
         * @return {Promise}
         */
        getRules: function (ruleIds) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_shipping_ajax_backend_rules_getRules', resolve, {
                    'package': 'quiqqer/shipping',
                    ruleIds  : JSON.encode(ruleIds),
                    onError  : reject
                });
            });
        },

        /**
         * Return the wanted rule
         *
         * @param {Integer} ruleId
         * @return {Promise}
         */
        getRule: function (ruleId) {
            return this.getRules([ruleId]).then(function (result) {
                return result[0];
            });
        }
    });
});
