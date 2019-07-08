/**
 * @module package/quiqqer/shipping/bin/backend/controls/ShippingEntry.List
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onLoad [self]
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
            '$onInject',
            '$openAddDialog',
            '$openDeleteDialog'
        ],

        options: {
            shippingId: false,
            name      : 'shipping-rules'
        },

        initialize: function (options) {
            this.parent(options);

            this.$Grid  = null;
            this.$Input = null;

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

            this.$Input = new Element('input', {
                type: 'hidden',
                name: this.getAttribute('name')
            }).inject(this.$Elm);

            return this.$Elm;
        },

        /**
         * refresh the input value
         */
        refreshInput: function () {
            if (!this.$Grid) {
                return;
            }

            var value = this.$Grid.getData().map(function (entry) {
                return parseInt(entry.id);
            });

            value = JSON.encode(value);

            this.$Input.value = value;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self      = this;
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

            require([
                'package/quiqqer/shipping/bin/backend/Shipping',
                'package/quiqqer/shipping/bin/backend/ShippingRules',
                'package/quiqqer/shipping/bin/backend/utils/ShippingUtils'
            ], function (Shipping, ShippingRules, Utils) {
                Shipping.getShippingEntry(
                    self.getAttribute('shippingId')
                ).then(function (result) {
                    var shippingRules = result.shipping_rules;

                    try {
                        shippingRules = JSON.decode(shippingRules);
                    } catch (e) {
                        shippingRules = [];
                    }

                    return ShippingRules.getRules(shippingRules);
                }).then(function (rules) {
                    self.$Grid.setData({
                        data: Utils.parseRulesDataForGrid(rules)
                    });

                    self.fireEvent('load', [self]);
                });
            });
        },

        /**
         * Add shipping rules to the shipping entry
         *
         * @param {Array} shippingRules - list of ids
         */
        addShippingRules: function (shippingRules) {
            var self = this;

            return new Promise(function (resolve) {
                require([
                    'package/quiqqer/shipping/bin/backend/ShippingRules'
                ], function (ShippingRules) {
                    ShippingRules.getRules(shippingRules).then(function (rules) {
                        var current = QUILocale.getCurrent();

                        rules.forEach(function (v, k) {
                            var title = '';

                            if (typeof v.title[current] !== 'undefined') {
                                title = v.title[current];
                            } else {
                                title = Object.values(v.title)[0];
                            }

                            rules[k].title = title;
                        });

                        self.$Grid.setData({
                            data: rules
                        });

                        self.refreshInput();
                        resolve();
                    });
                });
            });
        },

        /**
         * open rule window to add a rule to the shipping rules
         */
        $openAddDialog: function () {
            var self = this;

            require([
                'package/quiqqer/shipping/bin/backend/controls/shippingRules/ShippingRuleListWindow'
            ], function (ShippingRuleListWindow) {
                new ShippingRuleListWindow({
                    events: {
                        onSubmit: function (Win, selected) {
                            self.addShippingRules(selected);
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
