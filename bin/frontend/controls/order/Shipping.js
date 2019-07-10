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

        Binds: [
            '$onImport',
            '$onClick'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Input = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            this.getElm().addEvent('click', this.$onClick);

            this.$Input = this.getElm().getElement('input');

            if (this.$Input.checked) {
                this.getElm().addClass('selected');
            }
        },

        /**
         * event: on click
         */
        $onClick: function (event) {
            if (event.target.nodeName !== 'INPUT') {
                event.stop();
            }

            this.getElm()
                .getParent('.quiqqer-order-step-shipping-list')
                .getElements('.quiqqer-order-step-shipping-list-entry')
                .removeClass('selected');

            this.$Input.checked = true;
            this.getElm().addClass('selected');
        }
    });
});
