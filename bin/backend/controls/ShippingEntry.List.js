/**
 * @module package/quiqqer/shipping/bin/backend/controls/ShippingEntry.List
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/shipping/bin/backend/controls/ShippingEntry.List', [

    'qui/QUI',
    'qui/controls/Control',
    'controls/grid/Grid',
    'Locale'

], function (QUI, QUIControl, Grid, QUILocale) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/shipping/bin/backend/controls/ShippingEntry.List',

        Binds: [
            '$onInject'
        ],

        options: {
            shippingId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$Grid = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();

            this.$Elm.addClass('quiqqer-shipping-rules');


            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var Container = new Element('div').inject(this.getElm());
            var width     = Container.getSize().x - 5;

            this.$Grid = new Grid(Container, {
                height     : 300,
                width      : width,
                pagination : true,
                buttons    : [{
                    name     : 'add',
                    text     : QUILocale.get('quiqqer/quiqqer', 'add'),
                    textimage: 'fa fa-plus',
                    events   : {
                        onClick: this.$openAddDialog
                    }
                }, {
                    name     : 'delete',
                    text     : QUILocale.get('quiqqer/system', 'delete'),
                    textimage: 'fa fa-trash',
                    disabled : true,
                    events   : {
                        onClick: this.$openDeleteDialog
                    }
                }],
                columnModel: [{
                    header   : QUILocale.get('quiqqer/system', 'id'),
                    dataIndex: 'id',
                    dataType : 'number',
                    width    : 50
                }, {
                    header   : QUILocale.get('quiqqer/system', 'priority'),
                    dataIndex: 'priority',
                    dataType : 'number',
                    width    : 50
                }, {
                    header   : QUILocale.get('quiqqer/system', 'status'),
                    dataIndex: 'status',
                    dataType : 'button',
                    width    : 60
                }, {
                    header   : QUILocale.get('quiqqer/system', 'title'),
                    dataIndex: 'title',
                    dataType : 'string',
                    width    : 200
                }]
            });

            this.$Grid.setWidth(width);
        },

        /**
         * open rule window to add a rule to the shipping rules
         */
        $openAddDialog: function () {
            require([
                'package/quiqqer/shipping/bin/backend/controls/shippingRules/ShippingRuleListWindow'
            ], function (ShippingRuleListWindow) {
                new ShippingRuleListWindow({
                    events: {
                        onSubmit: function () {
                            console.log('submit');
                        }
                    }
                }).open();
            });
        },

        /**
         *
         */
        $openDeleteDialog: function () {

        }
    });
});
