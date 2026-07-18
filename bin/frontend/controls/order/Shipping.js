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

    'qui/controls/Control'

], function(QUIControl) {
    'use strict';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/shipping/bin/frontend/controls/order/Shipping',

        Binds: [
            '$onImport',
            '$onClick'
        ],

        initialize: function(options) {
            this.parent(options);

            this.$Input = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function() {
            var Elm = this.getElm();

            Elm.addEventListener('click', this.$onClick);
            this.$Input = Elm.querySelector('[data-name="shipping-option"]');

            if (this.$Input.checked) {
                Elm.classList.add('selected');
            }
        },

        /**
         * event: on click
         */
        $onClick: function(event) {
            if (event.target.nodeName !== 'INPUT') {
                event.preventDefault();
                event.stopPropagation();
            }

            var List = this.getElm().closest('[data-name="shipping-list"]');

            if (List) {
                List.querySelectorAll('[data-name="shipping-entry"]').forEach(function(Entry) {
                    Entry.classList.remove('selected');
                });
            }

            this.$Input.checked = true;
            this.getElm().classList.add('selected');
        }
    });
});
