/**
 * Shipping information control.
 * Show shipping information popup after click.
 *
 * @module package/quiqqer/shipping/bin/frontend/controls/ShippingInfo
 * @author www.pcsg.de (Michael Danielczok)
 */
define('package/quiqqer/shipping/bin/frontend/controls/ShippingInfo', [

    'qui/controls/Control',
    'Locale'

], function(QUIControl, QUILocale) {
    'use strict';

    return new Class({
        Extends: QUIControl,
        Type: 'package/quiqqer/shipping/bin/frontend/controls/ShippingInfo',

        Binds: [
            'showInfo',
            '$onImport'
        ],

        initialize: function(options) {
            this.parent(options);

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event on import
         */
        $onImport: function() {
            this.getElm().addEventListener('click', this.showInfo);
        },

        /**
         * Show shpping information popup
         *
         * @param event
         */
        showInfo: function(event) {
            event.preventDefault();
            event.stopPropagation();

            require([
                'qui/controls/windows/Popup'
            ], function(QUIPopup) {
                new QUIPopup({
                    'maxWidth': 700,
                    'maxHeight': 600,
                    'icon': 'fa fa-truck',
                    'title': QUILocale.get('quiqqer/shipping', 'frontend.shippingInfo.popup.title'),
                    'content': QUILocale.get('quiqqer/shipping', 'frontend.shippingInfo.popup.content'),
                    draggable: false,
                    resizable: false,
                    buttons: false
                }).open();
            });
        }
    });
});
