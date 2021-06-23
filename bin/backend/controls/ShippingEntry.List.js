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

    const lg = 'quiqqer/shipping';

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

            let value = this.$Grid.getData().map(function (entry) {
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
            this.fireEvent('refreshBegin', [this]);

            return new Promise((resolve) => {
                require([
                    'package/quiqqer/shipping/bin/backend/Shipping',
                    'package/quiqqer/shipping/bin/backend/ShippingRules',
                    'package/quiqqer/shipping/bin/backend/utils/ShippingUtils'
                ], (Shipping, ShippingRules, Utils) => {
                    let shippingRules = this.$Grid.getData().map(function (entry) {
                        return entry.id;
                    });

                    ShippingRules.getRules(shippingRules).then((rules) => {
                        this.$Grid.setData({
                            data: Utils.parseRulesDataForGrid(rules)
                        });

                        resolve();
                        this.fireEvent('refreshEnd', [this]);
                    }).catch((e) => {
                        console.log(e);
                        this.fireEvent('refreshEnd', [this]);
                    });
                });
            });
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            const Container = new Element('div').inject(this.getElm());
            const width     = Container.getSize().x - 5;

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
                    dataIndex: 'statusNode',
                    dataType : 'node',
                    width    : 60,
                    className: 'grid-align-center'
                }, {
                    header   : QUILocale.get('quiqqer/system', 'title'),
                    dataIndex: 'title',
                    dataType : 'string',
                    width    : 200
                }]
            });

            this.$Grid.setWidth(width);
            this.$Grid.addEvents({
                onClick: () => {
                    this.$Grid.getButtons().filter(function (Btn) {
                        return Btn.getAttribute('name') === 'remove';
                    })[0].enable();
                },

                onDblClick: () => {
                    require(['package/quiqqer/shipping/bin/backend/controls/shippingRules/RuleWindow'], (RuleWindow) => {
                        new RuleWindow({
                            ruleId: this.$Grid.getSelectedData()[0].id,
                            events: {
                                onUpdateEnd: () => {
                                    this.refreshInput();
                                    this.refresh();
                                }
                            }
                        }).open();
                    });
                }
            });

            require(['package/quiqqer/shipping/bin/backend/Shipping'], (Shipping) => {
                Shipping.getShippingEntry(this.getAttribute('shippingId')).then((result) => {
                    let shippingRules = result.shipping_rules;

                    try {
                        shippingRules = JSON.decode(shippingRules);
                    } catch (e) {
                        shippingRules = [];
                    }

                    if (typeOf(shippingRules) !== 'array') {
                        shippingRules = [];
                    }

                    let data = shippingRules.map(function (entry) {
                        return {
                            id: entry
                        };
                    });

                    this.$Grid.setData({
                        data: data
                    });

                    return this.refresh();
                }).then(() => {
                    this.fireEvent('load', [this]);
                });
            });
        },

        /**
         * Add shipping rules to the shipping entry
         *
         * @param {Array} shippingRules - list of ids
         */
        addShippingRules: function (shippingRules) {
            return new Promise((resolve) => {
                require(['package/quiqqer/shipping/bin/backend/ShippingRules'], (ShippingRules) => {
                    ShippingRules.getRules(shippingRules).then((rules) => {
                        const current     = QUILocale.getCurrent(),
                              currentData = this.$Grid.getData();

                        const isInCurrentData = function (id) {
                            id = parseInt(id);

                            for (let i = 0, len = currentData.length; i < len; i++) {
                                if (parseInt(currentData[i].id) === id) {
                                    return true;
                                }
                            }

                            return false;
                        };

                        rules.forEach(function (v, k) {
                            let title = '';

                            if (typeof v.title[current] !== 'undefined') {
                                title = v.title[current];
                            } else {
                                title = Object.values(v.title)[0];
                            }

                            rules[k].title = title;

                            // filter duplicated
                            if (isInCurrentData(rules[k].id)) {
                                return;
                            }

                            currentData.push(rules[k]);
                        });

                        this.$Grid.setData({
                            data: currentData
                        });

                        this.refreshInput();
                        this.refresh().then(resolve);
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
            let data = this.$Grid.getData();

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
            require([
                'package/quiqqer/shipping/bin/backend/controls/shippingRules/ShippingRuleListWindow'
            ], (ShippingRuleListWindow) => {
                new ShippingRuleListWindow({
                    events: {
                        onSubmit: (Win, selected) => {
                            this.addShippingRules(selected);
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
            const selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            const shippingIds = selected.map(function (entry) {
                return entry.id;
            });

            let idHtml = selected.map(function (entry) {
                return '<li>#' + entry.id + ' ' + entry.title + '</li>';
            });

            idHtml = '<ul>' + idHtml + '</ul>';

            require(['qui/controls/windows/Confirm'], (QUIConfirm) => {
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
                        onSubmit: () => {
                            this.removeShippingRules(shippingIds);
                        }
                    }
                }).open();
            });
        }
    });
});
