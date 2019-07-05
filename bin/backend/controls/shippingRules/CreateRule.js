/**
 * @module
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/shipping/bin/backend/controls/shippingRules/CreateRule', [

    'qui/QUI',
    'qui/controls/Control'

], function (QUI, QUIControl) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/shipping/bin/backend/controls/shippingRules/CreateRule',

        Binds: [
            '$onInject'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Create the DOMNode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();


            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {

        }
    });
});
