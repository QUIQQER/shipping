/**
 * @module package/quiqqer/shipping/bin/backend/controls/ShippingEntry.List
 * @author www.pcsg.de (Henning Leutz)
 *
 * Shipping rule list for the shipping entry
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

    var lg = 'quiqqer/shipping';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/shipping/bin/backend/controls/ShippingEntry.List',

        Binds: [
            '$onInject',
            '$openAddDialog',
            '$openRemoveDialog'
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
         * refresh the list
         *
         * @return {Promise}
         */
        refresh: function () {
            var self = this;

            this.fireEvent('refreshBegin', [this]);

            return new Promise(function (resolve) {
                require([
                    'package/quiqqer/shipping/bin/backend/Shipping',
                    'package/quiqqer/shipping/bin/backend/ShippingRules',
                    'package/quiqqer/shipping/bin/backend/utils/ShippingUtils'
                ], function (Shipping, ShippingRules, Utils) {
                    var shippingRules = self.$Grid.getData().map(function (entry) {
                        return entry.id;
                    });

                    ShippingRules.getRules(shippingRules).then(function (rules) {
                        self.$Grid.setData({
                            data: Utils.parseRulesDataForGrid(rules)
                        });

                        resolve();
                        self.fireEvent('refreshEnd', [self]);
                    }).catch(function (e) {
                        console.log(e);
                        self.fireEvent('refreshEnd', [self]);
                    });
                });
            });
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
                pagination : false,
                buttons    : [{
                    name     : 'add',
                    text     : QUILocale.get('quiqqer/quiqqer', 'add'),
                    textimage: 'fa fa-plus',
                    events   : {
                        onClick: this.$openAddDialog
                    }
                }, {
                    name     : 'remove',
                    text     : QUILocale.get('quiqqer/system', 'remove'),
                    textimage: 'fa fa-trash',
                    disabled : true,
                    events   : {
                        onClick: this.$openRemoveDialog
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
            this.$Grid.addEvents({
                onClick: function () {
                    self.$Grid.getButtons().filter(function (Btn) {
                        return Btn.getAttribute('name') === 'remove';
                    })[0].enable();
                },

                onDblClick: function () {
                    require(['package/quiqqer/shipping/bin/backend/controls/shippingRules/RuleWindow'], function (RuleWindow) {
                        new RuleWindow({
                            ruleId: self.$Grid.getSelectedData()[0].id,
                            events: {
                                onUpdateEnd: function () {
                                    self.refreshInput();
                                    self.refresh();
                                }
                            }
                        }).open();
                    });
                }
            });

            require(['package/quiqqer/shipping/bin/backend/Shipping'], function (Shipping) {
                Shipping.getShippingEntry(self.getAttribute('shippingId')).then(function (result) {
                    var shippingRules = result.shipping_rules;

                    try {
                        shippingRules = JSON.decode(shippingRules);
                    } catch (e) {
                        shippingRules = [];
                    }

                    var data = shippingRules.map(function (entry) {
                        return {
                            id: entry
                        };
                    });

                    self.$Grid.setData({
                        data: data
                    });

                    return self.refresh();
                }).then(function () {
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
                require(['package/quiqqer/shipping/bin/backend/ShippingRules'], function (ShippingRules) {
                    ShippingRules.getRules(shippingRules).then(function (rules) {
                        var current     = QUILocale.getCurrent(),
                            currentData = self.$Grid.getData();

                        rules.forEach(function (v, k) {
                            var title = '';

                            if (typeof v.title[current] !== 'undefined') {
                                title = v.title[current];
                            } else {
                                title = Object.values(v.title)[0];
                            }

                            rules[k].title = title;

                            currentData.push(rules[k]);
                        });

                        // filter duplicated


                        self.$Grid.setData({
                            data: currentData
                        });

                        self.refreshInput();
                        resolve();
                    });
                });
            });
        },

        /**
         * Remove a shipping rule from the shipping entry
         *
         * @param {Array} shippingRuleIds - ids of the shipping rules
         */
        removeShippingRules: function (shippingRuleIds) {
            var data = this.$Grid.getData();

            data = data.filter(function (entry) {
                return shippingRuleIds.indexOf(entry.id) === -1;
            });

            this.$Grid.setData({
                data: data
            });

            this.refreshInput();
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
         * Open the remove dialog
         * - The user has the possibility to remove a shipping rule from the shipping entry
         */
        $openRemoveDialog: function () {
            var self     = this,
                selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            var shippingIds = selected.map(function (entry) {
                return entry.id;
            });

            var idHtml = selected.map(function (entry) {
                return '<li>#' + entry.id + ' ' + entry.title + '</li>';
            });

            idHtml = '<ul>' + idHtml + '</ul>';

            require(['qui/controls/windows/Confirm'], function (QUIConfirm) {
                new QUIConfirm({
                    icon       : 'fa fa-trash',
                    texticon   : 'fa fa-trash',
                    title      : QUILocale.get(lg, 'window.shipping.entry.remove.rule.title'),
                    text       : QUILocale.get(lg, 'window.shipping.entry.remove.rule.text'),
                    information: QUILocale.get(lg, 'window.shipping.entry.remove.rule.information', {
                        ids: idHtml
                    }),
                    maxHeight  : 300,
                    maxWidth   : 600,
                    events     : {
                        onSubmit: function () {
                            self.removeShippingRules(shippingIds);
                        }
                    }
                }).open();
            });
        }
    });
});
