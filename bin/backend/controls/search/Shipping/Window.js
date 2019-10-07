/**
 * Opens a area search window to search for shipping entries
 *
 * @module package/quiqqer/shipping/bin/backend/controls/search/Shipping/Search
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/shipping/bin/backend/controls/search/Shipping/Window', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'package/quiqqer/shipping/bin/backend/Shipping',
    'Locale',

    'css!package/quiqqer/shipping/bin/backend/controls/search/Shipping/Window.css'

], function (QUI, QUIControl, QUIConfirm, Handler, QUILocale) {
    "use strict";

    var lg = 'quiqqer/shipping';

    return new Class({

        Extends: QUIConfirm,
        Type   : 'package/quiqqer/shipping/bin/backend/controls/search/Shipping/Window',

        Binds: [
            '$onOpen'
        ],

        options: {
            maxHeight: 600,
            maxWidth : 800,
            icon     : 'fa fa-globe',
            title    : QUILocale.get(lg, 'control.search.ShippingWindow.title'),
            autoclose: false,

            cancel_button: {
                text     : QUILocale.get('quiqqer/system', 'cancel'),
                textimage: 'fa fa-remove'
            },
            ok_button    : {
                text     : QUILocale.get('quiqqer/system', 'accept'),
                textimage: 'fa fa-truck'
            }
        },

        initialize: function (options) {
            this.parent(options);

            this.$Result = null;

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * Return the DOMNode Element
         *
         * @returns {HTMLDivElement}
         */
        $onOpen: function (Win) {
            var self    = this,
                Content = Win.getContent();

            Win.Loader.show();

            Content.set('html', '');
            Content.addClass('shipping-search');

            this.$ResultContainer = new Element('div', {
                'class': 'shipping-search-resultContainer'
            }).inject(Content);

            require([
                'package/quiqqer/shipping/bin/backend/controls/search/Shipping/Result'
            ], function (Result) {
                self.$Result = new Result({
                    events: {
                        onDblClick: function () {
                            self.submit();
                        }
                    }
                }).inject(self.$ResultContainer);

                self.$Result.resize();

                Handler.getShippingList().then(function (result) {
                    self.$Result.setData(result);
                    Win.Loader.hide();
                });
            });
        },

        /**
         * Submit
         */
        submit: function () {
            if (!this.$Result) {
                return;
            }

            this.fireEvent('submit', [this, this.$Result.getSelected()]);

            if (this.getAttribute('autoclose')) {
                this.close();
            }
        }
    });
});
