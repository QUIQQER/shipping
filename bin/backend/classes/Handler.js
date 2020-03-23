/**
 * @module package/quiqqer/shipping/bin/backend/classes/Handler
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onShippingDeactivate [self, shippingId, data]
 * @event onShippingActivate [self, shippingId, data]
 * @event onShippingDelete [self, shippingId]
 * @event onShippingCreate [self, shippingId]
 * @event onShippingUpdate [self, shippingId, data]
 */
define('package/quiqqer/shipping/bin/backend/classes/Handler', [

    'qui/QUI',
    'qui/classes/DOM',
    'Ajax'

], function (QUI, QUIDOM, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/quiqqer/shipping/bin/Manager',

        initialize: function (options) {
            this.parent(options);

            this.$shippings = null;
        },

        /**
         * Return active shipping
         *
         * @return {Promise}
         */
        getShippingList: function () {
            if (this.$shippings) {
                return window.Promise.resolve(this.$shippings);
            }

            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_shipping_ajax_backend_getShippingList', function (result) {
                    self.$shippings = result;
                    resolve(self.$shippings);
                }, {
                    'package': 'quiqqer/shipping',
                    onError  : reject
                });
            });
        },

        /**
         * Return the shipping entry data
         *
         * @param {String|Number} shippingId
         * @return {Promise}
         */
        getShippingEntry: function (shippingId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_shipping_ajax_backend_getShippingEntry', resolve, {
                    'package' : 'quiqqer/shipping',
                    onError   : reject,
                    shippingId: shippingId
                });
            });
        },

        /**
         * Return all available shipping methods
         *
         * @return {Promise}
         */
        getShippingTypes: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_shipping_ajax_backend_getShippingTypes', resolve, {
                    'package': 'quiqqer/shipping',
                    onError  : reject
                });
            });
        },

        /**
         * Create a new inactive shipping type
         *
         * @param {String} shippingType - Hash of the shipping type
         * @return {Promise}
         */
        createShipping: function (shippingType) {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_shipping_ajax_backend_create', function (shippingId) {
                    self.$shippings = null;

                    require([
                        'package/quiqqer/translator/bin/Translator'
                    ], function (Translator) {
                        Translator.refreshLocale().then(function () {
                            self.fireEvent('shippingCreate', [self, shippingId]);
                            resolve(shippingId);
                        });
                    });
                }, {
                    'package'   : 'quiqqer/shipping',
                    onError     : reject,
                    shippingType: shippingType
                });
            });
        },

        /**
         * Update a shipping
         *
         * @param {Number|String} shippingId - Shipping ID
         * @param {Object} data - Data of the shipping
         * @return {Promise}
         */
        updateShipping: function (shippingId, data) {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_shipping_ajax_backend_update', function (result) {
                    self.$shippings = null;

                    require([
                        'package/quiqqer/translator/bin/Translator'
                    ], function (Translator) {
                        Translator.refreshLocale().then(function () {
                            self.fireEvent('shippingUpdate', [self, shippingId, result]);
                            resolve(result);
                        });
                    });
                }, {
                    'package' : 'quiqqer/shipping',
                    onError   : reject,
                    shippingId: shippingId,
                    data      : window.JSON.encode(data)
                });
            });
        },

        /**
         *
         * @param {String|Number} shippingId
         * @return {Promise}
         */
        deleteShipping: function (shippingId) {
            var self = this;

            return new Promise(function (resolve, reject) {
                self.$shippings = null;

                QUIAjax.post('package_quiqqer_shipping_ajax_backend_delete', function () {
                    self.fireEvent('shippingDelete', [self, shippingId]);
                    resolve();
                }, {
                    'package' : 'quiqqer/shipping',
                    onError   : reject,
                    shippingId: shippingId
                });
            });
        },

        /**
         * Activate a shipping
         *
         * @param {String|Number} shippingId
         * @return {Promise}
         */
        activateShipping: function (shippingId) {
            var self = this;

            return new Promise(function (resolve, reject) {
                self.$shippings = null;

                QUIAjax.post('package_quiqqer_shipping_ajax_backend_activate', function (result) {
                    self.fireEvent('shippingActivate', [self, shippingId, result]);
                    resolve(result);
                }, {
                    'package' : 'quiqqer/shipping',
                    onError   : reject,
                    shippingId: shippingId
                });
            });
        },

        /**
         * Deactivate a shipping
         *
         * @param {String|Number} shippingId
         * @return {Promise}
         */
        deactivateShipping: function (shippingId) {
            var self = this;

            return new Promise(function (resolve, reject) {
                self.$shippings = null;

                QUIAjax.post('package_quiqqer_shipping_ajax_backend_deactivate', function (result) {
                    self.fireEvent('shippingDeactivate', [self, shippingId, result]);
                    resolve(result);
                }, {
                    'package' : 'quiqqer/shipping',
                    onError   : reject,
                    shippingId: shippingId
                });
            });
        }
    });
});
