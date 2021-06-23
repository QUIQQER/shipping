/**
 * @module package/quiqqer/shipping/bin/backend/controls/ShippingSelect
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/shipping/bin/backend/controls/ShippingSelect', [

    'qui/QUI',
    'qui/controls/elements/Select',
    'package/quiqqer/shipping/bin/backend/Shipping',
    'Locale',

    'css!package/quiqqer/shipping/bin/backend/controls/ShippingSelect.css'

], function (QUI, QUIElementSelect, Handler, QUILocale) {
    "use strict";

    return new Class({

        Extends: QUIElementSelect,
        Type   : 'package/quiqqer/shipping/bin/backend/controls/ShippingSelect',

        Binds: [
            '$onCreate',
            '$onSearchButtonClick'
        ],

        initialize: function (options) {
            this.parent(options);

            this.setAttribute('icon', 'fa fa-truck');
            this.setAttribute('child', 'package/quiqqer/shipping/bin/backend/controls/ShippingSelectItem');
            this.setAttribute('searchbutton', true);

            this.setAttribute(
                'placeholder',
                QUILocale.get('quiqqer/shipping', 'control.select.placeholder')
            );

            this.addEvents({
                onSearchButtonClick: this.$onSearchButtonClick,
                onCreate           : this.$onCreate
            });
        },

        /**
         * Event: onCreate
         */
        $onCreate: function () {
            this.getElm().getParent().addClass('quiqqer-shipping-select');
        },

        /**
         * event : on search button click
         *
         * @param {Object} self - select object
         * @param {Object} Btn - button object
         */
        $onSearchButtonClick: function (self, Btn) {
            Btn.setAttribute('icon', 'fa fa-spinner fa-spin');

            require(['package/quiqqer/shipping/bin/backend/controls/search/Shipping/Window'], (Window) => {
                new Window({
                    autoclose: true,
                    multiple : this.getAttribute('multiple'),
                    events   : {
                        onSubmit: (Win, data) => {
                            data = data.map(function (Entry) {
                                return parseInt(Entry.id);
                            });

                            for (let i = 0, len = data.length; i < len; i++) {
                                this.addItem(data[i]);
                            }
                        }
                    }
                }).open();

                Btn.setAttribute('icon', 'fa fa-search');
            });
        }
    });
});
