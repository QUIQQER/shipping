/**
 * @module package/quiqqer/shipping/bin/backend/controls/shippingRules/ShippingRuleList
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/shipping/bin/backend/controls/shippingRules/ShippingRuleList', [

    'qui/QUI',
    'qui/controls/Control',
    'package/quiqqer/shipping/bin/backend/ShippingRules',
    'package/quiqqer/shipping/bin/backend/utils/ShippingUtils',
    'controls/grid/Grid',
    'Locale'

], function (QUI, QUIControl, ShippingRules, ShippingUtils, Grid, QUILocale) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/shipping/bin/backend/controls/shippingRules/ShippingRuleList',

        options: {
            multiple: true
        },

        Binds: [
            '$onInject',
            '$openCreateDialog',
            '$openDeleteDialog',
            'refresh',
            'getSelected'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Grid = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Resize the element
         */
        resize: function () {
            var size   = this.getElm().getSize();
            var width  = size.x - 5;
            var height = size.y;

            this.$Grid.setWidth(width);
            this.$Grid.setHeight(height);
        },

        /**
         * Create the DOMNode element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();
            this.$Elm.addClass('quiqqer-shipping-rule-list');

            this.$Elm.setStyles({
                height: '100%',
                width : '100%'
            });

            var Container = new Element('div', {
                styles: {
                    height: '100%',
                    width : '100%'
                }
            }).inject(this.getElm());

            var size   = Container.getSize();
            var width  = size.x - 5;
            var height = size.y;

            this.$Grid = new Grid(Container, {
                height           : height,
                width            : width,
                pagination       : true,
                multipleSelection: this.getAttribute('multiple'),
                buttons          : [{
                    name     : 'create',
                    text     : QUILocale.get('quiqqer/quiqqer', 'create'),
                    textimage: 'fa fa-plus',
                    events   : {
                        onClick: this.$openCreateDialog
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
                columnModel      : [{
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

            this.$Grid.addEvents({
                onRefresh: this.refresh
            });

            return this.$Elm;
        },

        /**
         * Refresh the list
         *
         * @return
         */
        refresh: function () {
            var self = this;

            this.fireEvent('refreshBegin', self);

            ShippingRules.getList().then(function (rules) {
                self.fireEvent('refresh', [self, rules]);

                self.$Grid.setData({
                    data: ShippingUtils.parseRulesDataForGrid(rules)
                });

                return rules;
            });
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            this.refresh();
        },

        /**
         * Return the selected rules
         *
         * @return {Array}
         */
        getSelected: function () {
            if (!this.$Grid) {
                return [];
            }

            return this.$Grid.getSelectedData().map(function (entry) {
                return parseInt(entry.id);
            });
        },

        /**
         * event: open create dialog
         */
        $openCreateDialog: function () {
            var self = this;

            this.fireEvent('openCreateRuleWindow', [this]);

            require([
                'package/quiqqer/shipping/bin/backend/controls/shippingRules/CreateRuleWindow'
            ], function (CreateRuleWindow) {
                new CreateRuleWindow({
                    events: {
                        onClose: function () {
                            self.fireEvent('closeCreateRuleWindow', [self]);
                        }
                    }
                }).open();
            });
        },

        /**
         * event: open delete dialog
         */
        $openDeleteDialog: function () {

        }
    });
});
