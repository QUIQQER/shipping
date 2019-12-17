/**
 * @module package/quiqqer/shipping/bin/backend/classes/ShippingStatus
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/shipping/bin/backend/classes/ShippingStatus', [

    'qui/QUI',
    'qui/classes/DOM',
    'Ajax'

], function (QUI, QUIDOM, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/quiqqer/shipping/bin/backend/classes/ShippingStatus',

        initialize: function (options) {
            this.parent(options);
        },

        /**
         * Return the processing status list for a grid
         *
         * @return {Promise}
         */
        getList: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_shipping_ajax_backend_shippingStatus_list', resolve, {
                    'package': 'quiqqer/shipping',
                    onError  : reject
                });
            });
        },

        /**
         * Return next available ID
         *
         * @return {Promise}
         */
        getNextId: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_shipping_ajax_backend_shippingStatus_getNextId', resolve, {
                    'package': 'quiqqer/shipping',
                    onError  : reject
                });
            });
        },

        /**
         * Create a new processing status
         *
         * @param {String|Number} id - Processing Status ID
         * @param {String} color
         * @param {Object} title - {de: '', en: ''}
         * @param {Boolean} notification
         * @return {Promise}
         */
        createShippingStatus: function (id, color, title, notification) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_shipping_ajax_backend_shippingStatus_create', function (result) {
                    require([
                        'package/quiqqer/translator/bin/Translator'
                    ], function (Translator) {
                        Translator.refreshLocale().then(function () {
                            resolve(result);
                        });
                    });
                }, {
                    'package'   : 'quiqqer/shipping',
                    id          : id,
                    color       : color,
                    title       : JSON.encode(title),
                    notification: notification ? 1 : 0,
                    onError     : reject
                });
            });
        },

        /**
         * Delete a processing status
         *
         * @param {String|Number} id - Processing Status ID
         * @return {Promise}
         */
        deleteShippingStatus: function (id) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_shipping_ajax_backend_shippingStatus_delete', function () {
                    require([
                        'package/quiqqer/translator/bin/Translator'
                    ], function (Translator) {
                        Translator.refreshLocale().then(function () {
                            resolve();
                        });
                    });
                }, {
                    'package': 'quiqqer/shipping',
                    id       : id,
                    onError  : reject
                });
            });
        },

        /**
         * Return the status data
         *
         * @param {String|Number} id - Processing Status ID
         * @return {Promise}
         */
        getShippingStatus: function (id) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_shipping_ajax_backend_shippingStatus_get', resolve, {
                    'package': 'quiqqer/shipping',
                    id       : id,
                    onError  : reject
                });
            });
        },

        /**
         * Return the status data
         *
         * @param {String|Number} id - Processing Status ID
         * @param {String} color
         * @param {Object} title - {de: '', en: ''}
         * @param {Boolean} notification
         * @return {Promise}
         */
        updateShippingStatus: function (id, color, title, notification) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_shipping_ajax_backend_shippingStatus_update', resolve, {
                    'package'   : 'quiqqer/shipping',
                    id          : id,
                    color       : color,
                    title       : JSON.encode(title),
                    onError     : reject,
                    notification: notification ? 1 : 0
                });
            });
        },

        /**
         * Get status change notification text for a specific shipping
         *
         * @param {Number} id - ShippingStatus ID
         * @param {Number} shippingId - shipping ID
         * @return {Promise}
         */
        getNotificationText: function (id, shippingId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_shipping_ajax_backend_shippingStatus_getNotificationText', resolve, {
                    'package' : 'quiqqer/shipping',
                    id        : id,
                    shippingId: shippingId,
                    onError   : reject
                });
            });
        }
    });
});
