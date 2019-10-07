/**
 * Search for shipping entries
 *
 * @module package/quiqqer/shipping/bin/backend/controls/search/Shipping/Result
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onLoaded
 * @event onDblClick [self]
 */
define('package/quiqqer/shipping/bin/backend/controls/search/Shipping/Result', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'controls/grid/Grid',
    'Locale'

], function (QUI, QUIControl, QUIButton, Grid, QUILocale) {
    "use strict";

    var lg = 'quiqqer/shipping';

    return new Class({
        Extends: QUIControl,
        Type   : 'package/quiqqer/shipping/bin/backend/controls/search/Shipping/Result',

        Binds: [
            '$onInject'
        ],

        options: {
            multipleSelection: true
        },

        initialize: function (options) {
            this.parent(options);

            this.$Grid = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Return the DOMNode Element
         * @returns {HTMLDivElement}
         */
        create: function () {
            var Elm = this.parent();

            Elm.set('html', '');

            Elm.setStyles({
                'float' : 'left',
                'height': '100%',
                'width' : '100%'
            });

            var Container = new Element('div').inject(Elm);

            this.$Grid = new Grid(Container, {
                filterInput      : true,
                multipleSelection: this.getAttribute('multipleSelection'),
                columnModel      : [{
                    header   : QUILocale.get('quiqqer/system', 'id'),
                    dataIndex: 'id',
                    dataType : 'number',
                    width    : 30
                }, {
                    header   : QUILocale.get('quiqqer/system', 'priority'),
                    dataIndex: 'priority',
                    dataType : 'number',
                    width    : 50
                }, {
                    header   : QUILocale.get('quiqqer/system', 'title'),
                    dataIndex: 'currentTitle',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get('quiqqer/system', 'workingtitle'),
                    dataIndex: 'currentWorkingTitle',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'shipping.type'),
                    dataIndex: 'shippingType_display',
                    dataType : 'string',
                    width    : 200
                }]
            });

            this.$Grid.addEvent('onDblClick', function () {
                this.fireEvent('dblClick', [this]);
            }.bind(this));

            return Elm;
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            this.fireEvent('loaded');
        },

        /**
         * Set data to the grid
         *
         * @param {Object} data - grid data
         */
        setData: function (data) {
            if (!this.$Grid) {
                return;
            }

            for (var i = 0, len = data.length; i < len; i++) {
                if ("shippingType" in data[i] && data[i].shippingType) {
                    data[i].shippingType_display = data[i].shippingType.title;
                }
            }

            this.$Grid.setData({
                data: data
            });
        },

        /**
         * Return the selected data
         *
         * @returns {Array}
         */
        getSelected: function () {
            if (!this.$Grid) {
                return [];
            }

            return this.$Grid.getSelectedData();
        },

        /**
         * Resize the control
         *
         * @return {Promise}
         */
        resize: function () {
            var size = this.getElm().getSize();

            this.$Grid.setWidth(size.x);
            this.$Grid.setHeight(size.y);

            return this.$Grid.resize();
        }
    });
});
