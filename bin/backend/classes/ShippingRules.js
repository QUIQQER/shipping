/**
 * @module package/quiqqer/shipping/bin/backend/classes/ShippingRules
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onCreate [self, ruleId]
 * @event onUpdate [self, ruleId]
 * @event onDelete [self, ruleIds]
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
        getList: function (options) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_shipping_ajax_backend_rules_getList', resolve, {
                    'package': 'quiqqer/shipping',
                    options  : JSON.encode(options),
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
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_shipping_ajax_backend_rules_create', function (ruleId) {
                    resolve(ruleId);
                    self.fireEvent('create', [self, ruleId]);
                }, {
                    'package': 'quiqqer/shipping',
                    rules    : JSON.encode(rules),
                    onError  : reject
                });
            });
        },

        /**
         * Create a new rule
         *
         * @param {Number} ruleId
         * @param {Object} data
         * @return {Promise}
         */
        update: function (ruleId, data) {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_shipping_ajax_backend_rules_update', function () {
                    resolve();
                    self.fireEvent('update', [self, ruleId]);
                }, {
                    'package': 'quiqqer/shipping',
                    ruleId   : ruleId,
                    data     : JSON.encode(data),
                    onError  : reject
                });
            });
        },

        /**
         * Create a new rule
         *
         * @param {Number|Array} ruleIds
         * @return {Promise}
         */
        delete: function (ruleIds) {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_shipping_ajax_backend_rules_delete', function () {
                    resolve();
                    self.fireEvent('delete', [self, ruleIds]);
                }, {
                    'package': 'quiqqer/shipping',
                    ruleIds  : JSON.encode(ruleIds),
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
