/**
 * @module package/quiqqer/shipping/bin/backend/controls/Areas
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/shipping/bin/backend/controls/ShippingSelectItem', [

    'qui/QUI',
    'qui/controls/elements/SelectItem',
    'package/quiqqer/shipping/bin/backend/Shipping'

], function(QUI, QUIElementSelectItem, Handler) {
    'use strict';

    return new Class({

        Extends: QUIElementSelectItem,
        Type: 'package/quiqqer/shipping/bin/backend/controls/ShippingSelectItem',

        Binds: [
            'refresh'
        ],

        initialize: function(options) {
            this.parent(options);

            this.setAttribute('icon', 'fa fa-truck');
        },

        /**
         * Refresh the display
         *
         * @returns {Promise}
         */
        refresh: function() {
            var self = this;

            return Handler.getShippingEntry(this.getAttribute('id')).then(function(Shipping) {
                self.$Text.set({
                    html: Shipping.currentTitle
                });
            });
        }
    });
});