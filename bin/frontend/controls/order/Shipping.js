/**
 * @module package/quiqqer/shipping/bin/frontend/controls/order/Shipping
 * @author www.pcsg.de (Henning Leutz)
 *
 */
/**
 * @module
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/shipping/bin/frontend/controls/order/Shipping', [

    'qui/QUI',
    'qui/controls/Control'

], function (QUI, QUIControl) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/shipping/bin/frontend/controls/order/Shipping',

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject,
                onImport: this.$onImport
            });
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            console.log('$onInject');
        },

        /**
         * event: on import
         */
        $onImport: function () {
            console.log('$onImport');
        }
    });
});
