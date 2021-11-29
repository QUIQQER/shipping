/**
 * Shipping information control.
 * Show shipping information popup after click.
 *
 * @module package/quiqqer/shipping/bin/frontend/controls/ShippingInfo
 * @author www.pcsg.de (Michael Danielczok)
 */
define('package/quiqqer/shipping/bin/frontend/controls/ShippingInfo', [

    'qui/QUI',
    'qui/controls/Control',
    'Locale'

], function (QUI, QUIControl, QUILocale) {
    "use strict";

    return new Class({
        Extends: QUIControl,
        Type   : 'package/quiqqer/shipping/bin/frontend/controls/ShippingInfo',

        Binds: [
            'showInfo',
            '$onImport'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event on import
         */
        $onImport: function () {
            this.getElm().addEvent('click', this.showInfo);
        },

        /**
         * Show shpping information popup
         *
         * @param event
         */
        showInfo: function (event) {
            event.stop();

            require([
                'qui/controls/windows/Popup'
            ], function (QUIConfirm) {

                new QUIConfirm({
                    'maxWidth' : 700,
                    'maxHeight': 600,
                    'icon'     : false,
                    'title'    : false,
                    'content'  : QUILocale.get('quiqqer/shipping', 'frontend.shippingInfo.popup.content'),
                    draggable  : false,
                    resizable  : false
                }).open();
            });
        }
    });
});
