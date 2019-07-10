/**
 * @module package/quiqqer/shipping/bin/backend/controls/shippingRules/ShippingRuleList
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/shipping/bin/backend/controls/shippingRules/ShippingRuleList', [

    'qui/QUI',
    'qui/controls/Control',
    'package/quiqqer/shipping/bin/backend/ShippingRules',
    'package/quiqqer/shipping/bin/backend/controls/shippingRules/RuleWindow',
    'package/quiqqer/shipping/bin/backend/utils/ShippingUtils',
    'controls/grid/Grid',
    'Locale'

], function (QUI, QUIControl, ShippingRules, RuleWindow, ShippingUtils, Grid, QUILocale) {
    "use strict";

    var lg = 'quiqqer/shipping';

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
            '$openEditDialog',
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

            var self   = this,
                size   = Container.getSize(),
                width  = size.x - 5,
                height = size.y;

            this.$Grid = new Grid(Container, {
                height           : height,
                width            : width,
                pagination       : true,
                multipleSelection: this.getAttribute('multiple'),
                serverSort       : true,
                buttons          : [{
                    name     : 'create',
                    text     : QUILocale.get('quiqqer/quiqqer', 'create'),
                    textimage: 'fa fa-plus',
                    events   : {
                        onClick: this.$openCreateDialog
                    }
                }, {
                    name     : 'edit',
                    text     : QUILocale.get('quiqqer/system', 'edit'),
                    textimage: 'fa fa-edit',
                    disabled : true,
                    events   : {
                        onClick: this.$openEditDialog
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
                    dataIndex: 'statusNode',
                    dataType : 'node',
                    width    : 60
                }, {
                    header   : QUILocale.get('quiqqer/system', 'title'),
                    dataIndex: 'title',
                    dataType : 'string',
                    width    : 200,
                    sortable : false
                }, {
                    header   : QUILocale.get(lg, 'shipping.edit.template.discount'),
                    dataIndex: 'discount',
                    dataType : 'number',
                    width    : 100,
                    className: 'grid-align-right'
                }, {
                    header   : QUILocale.get(lg, 'shipping.edit.template.discount.type'),
                    dataIndex: 'discount_type_text',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'shipping.edit.template.discount.type'),
                    dataIndex: 'discount_type',
                    dataType : 'string',
                    hidden   : true
                }]
            });

            this.$Grid.addEvents({
                onRefresh : this.refresh,
                onClick   : function () {
                    var buttons  = self.$Grid.getButtons();
                    var selected = self.$Grid.getSelectedData();

                    var Edit = buttons.filter(function (Btn) {
                        return Btn.getAttribute('name') === 'edit';
                    })[0];

                    buttons.filter(function (Btn) {
                        return Btn.getAttribute('name') === 'delete';
                    })[0].enable();

                    if (selected.length === 1) {
                        Edit.enable();
                    } else {
                        Edit.disable();
                    }
                },
                onDblClick: this.$openEditDialog
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

            ShippingRules.getList(this.$Grid.options).then(function (result) {
                self.fireEvent('refresh', [self, result, result.data]);

                result.data = ShippingUtils.parseRulesDataForGrid(result.data);
                self.$Grid.setData(result);

                var buttons = self.$Grid.getButtons();

                buttons.filter(function (Btn) {
                    return Btn.getAttribute('name') === 'delete';
                })[0].disable();

                buttons.filter(function (Btn) {
                    return Btn.getAttribute('name') === 'edit';
                })[0].disable();

                self.fireEvent('refreshEnd', [self, result, result.data]);

                return result;
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
         * event: open edit dialog
         */
        $openEditDialog: function () {
            var self = this;

            new RuleWindow({
                ruleId: this.$Grid.getSelectedData()[0].id,
                events: {
                    onClose  : function () {
                        self.refresh();
                    },
                    updateEnd: function (Win) {
                        Win.close();
                    }
                }
            }).open();
        },

        /**
         * event: open delete dialog
         */
        $openDeleteDialog: function () {
            var self     = this,
                selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            var ruleIds = selected.map(function (entry) {
                return entry.id;
            });

            var idHtml = selected.map(function (entry) {
                return '<li>#' + entry.id + ' ' + entry.title + '</li>';
            }).join('');

            idHtml = '<ul>' + idHtml + '</ul>';

            require(['qui/controls/windows/Confirm'], function (QUIConfirm) {
                new QUIConfirm({
                    icon       : 'fa fa-trash',
                    texticon   : 'fa fa-trash',
                    title      : QUILocale.get(lg, 'window.shipping.entry.delete.rule.title'),
                    text       : QUILocale.get(lg, 'window.shipping.entry.delete.rule.text'),
                    information: QUILocale.get(lg, 'window.shipping.entry.delete.rule.information', {
                        ids: idHtml
                    }),
                    maxHeight  : 300,
                    maxWidth   : 600,
                    autoclose  : true,
                    ok_button  : {
                        text     : QUILocale.get(lg, 'window.shipping.entry.delete.rule.delete'),
                        textimage: 'fa fa-trash'
                    },
                    events     : {
                        onSubmit: function (Win) {
                            Win.Loader.show();

                            ShippingRules.delete(ruleIds).then(function () {
                                self.fireEvent('deleteRule', [self]);
                                Win.close();

                                self.refresh();
                            });
                        }
                    }
                }).open();
            });
        }
    });
});
