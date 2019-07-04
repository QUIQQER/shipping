/**
 * @module package/quiqqer/shipping/bin/backend/controls/Shipping
 * @author www.pcsg.de (Henning Leutz)
 *
 * Shipping Panel
 */
define('package/quiqqer/shipping/bin/backend/controls/Shipping', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/windows/Confirm',
    'qui/controls/buttons/Button',
    'package/quiqqer/shipping/bin/backend/Shipping',
    'controls/grid/Grid',
    'Mustache',
    'Locale'

], function (QUI, QUIPanel, QUIConfirm, QUIButton, Shipping, Grid, Mustache, QUILocale) {
    "use strict";

    var lg      = 'quiqqer/shipping';
    var current = QUILocale.getCurrent();

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/shipping/bin/backend/controls/Shipping',

        Binds: [
            'refresh',
            '$onCreate',
            '$onInject',
            '$onResize',
            '$onDestroy',
            '$onEditClick',
            '$onShippingChange',
            '$openCreateDialog',
            '$openDeleteDialog',
            '$refreshButtonStatus'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Grid = null;

            this.setAttributes({
                icon : 'fa fa-credit-card-alt',
                title: QUILocale.get(lg, 'menu.erp.shipping.title')
            });

            this.addEvents({
                onCreate : this.$onCreate,
                onInject : this.$onInject,
                onResize : this.$onResize,
                onDestroy: this.$onDestroy
            });

            Shipping.addEvents({
                onShippingDeactivate: this.$onShippingChange,
                onShippingActivate  : this.$onShippingChange,
                onShippingDelete    : this.$onShippingChange,
                onShippingCreate    : this.$onShippingChange,
                onShippingUpdate    : this.$onShippingChange
            });
        },

        /**
         * Refresh the value and the display
         */
        refresh: function () {
            if (!this.$Elm) {
                return;
            }

            this.Loader.show();

            var self = this;

            this.$Grid.getButtons().filter(function (Btn) {
                return Btn.getAttribute('name') === 'edit';
            })[0].disable();

            this.$Grid.getButtons().filter(function (Btn) {
                return Btn.getAttribute('name') === 'delete';
            })[0].disable();


            Shipping.getShippingList().then(function (result) {
                var toggle = function (Btn) {
                    var data       = Btn.getAttribute('data'),
                        shippingId = data.id,
                        status     = parseInt(data.active);


                    Btn.setAttribute('icon', 'fa fa-spinner fa-spin');

                    if (status) {
                        Shipping.deactivateShipping(shippingId);
                        return;
                    }

                    Shipping.activateShipping(shippingId);
                };

                for (var i = 0, len = result.length; i < len; i++) {
                    if (parseInt(result[i].active)) {
                        result[i].status = {
                            icon  : 'fa fa-check',
                            styles: {
                                lineHeight: 20,
                                padding   : 0,
                                width     : 20
                            },
                            events: {
                                onClick: toggle
                            }
                        };
                    } else {
                        result[i].status = {
                            icon  : 'fa fa-remove',
                            styles: {
                                lineHeight: 20,
                                padding   : 0,
                                width     : 20
                            },
                            events: {
                                onClick: toggle
                            }
                        };
                    }

                    result[i].shippingType_display = '';

                    result[i].title        = result[i].title[current];
                    result[i].workingTitle = result[i].workingTitle[current];

                    if ("shippingType" in result[i] && result[i].shippingType) {
                        result[i].shippingType_display = result[i].shippingType.title;
                    }
                }

                self.$Grid.setData({
                    data: result
                });

                self.Loader.hide();
            });
        },

        /**
         * event: on create
         */
        $onCreate: function () {
            var Container = new Element('div', {
                styles: {
                    minHeight: 300,
                    width    : '100%'
                }
            }).inject(this.getContent());

            this.$Grid = new Grid(Container, {
                buttons    : [{
                    name     : 'add',
                    text     : QUILocale.get('quiqqer/quiqqer', 'add'),
                    textimage: 'fa fa-plus',
                    events   : {
                        onClick: this.$openCreateDialog
                    }
                }, {
                    type: 'separator'
                }, {
                    name     : 'edit',
                    text     : QUILocale.get('quiqqer/quiqqer', 'edit'),
                    textimage: 'fa fa-edit',
                    disabled : true,
                    events   : {
                        onClick: this.$onEditClick
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
                }, {
                    header   : QUILocale.get('quiqqer/system', 'workingtitle'),
                    dataIndex: 'workingTitle',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get('quiqqer/system', 'id'),
                    dataIndex: 'id',
                    dataType : 'number',
                    width    : 30
                }, {
                    header   : QUILocale.get(lg, 'shipping.type'),
                    dataIndex: 'shippingType_display',
                    dataType : 'string',
                    width    : 200
                }]
            });

            this.$Grid.addEvents({
                onRefresh : this.refresh,
                onClick   : this.$refreshButtonStatus,
                onDblClick: this.$onEditClick
            });
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            this.refresh();
        },

        /**
         * event: on destroy
         */
        $onDestroy: function () {
            Shipping.removeEvents({
                onShippingDeactivate: this.$onShippingChange,
                onShippingActivate  : this.$onShippingChange,
                onShippingDelete    : this.$onShippingChange,
                onShippingCreate    : this.$onShippingChange,
                onShippingUpdate    : this.$onShippingChange
            });
        },

        /**
         * event : on resize
         */
        $onResize: function () {
            if (!this.$Grid) {
                return;
            }

            var Body = this.getContent();

            if (!Body) {
                return;
            }

            var size = Body.getSize();
            this.$Grid.setHeight(size.y - 40);
            this.$Grid.setWidth(size.x - 40);
        },

        /**
         * event : on shipping change
         * if a shipping changed
         */
        $onShippingChange: function () {
            this.refresh();
        },

        /**
         * open the edit dialog
         */
        openShipping: function (shippingId) {
            require([
                'package/quiqqer/shipping/bin/backend/controls/ShippingEntry',
                'utils/Panels'
            ], function (ShippingEntry, Utils) {
                Utils.openPanelInTasks(
                    new ShippingEntry({
                        shippingId: shippingId
                    })
                );
            });
        },

        /**
         * event: on edit
         */
        $onEditClick: function () {
            var data = this.$Grid.getSelectedData();

            if (data.length) {
                this.openShipping(data[0].id);
            }
        },

        /**
         * open the add dialog
         */
        $openCreateDialog: function () {
            var self = this;

            new QUIConfirm({
                icon       : 'fa fa-plus',
                texticon   : 'fa fa-plus',
                title      : QUILocale.get(lg, 'window.create.title'),
                text       : QUILocale.get(lg, 'window.create.title'),
                information: QUILocale.get(lg, 'window.create.information'),
                autoclose  : false,
                maxHeight  : 400,
                maxWidth   : 600,
                events     : {
                    onOpen  : function (Win) {
                        var Content = Win.getContent(),
                            Body    = Content.getElement('.textbody');

                        Win.Loader.show();

                        var Container = new Element('div', {
                            html  : QUILocale.get(lg, 'window.create.shippingType'),
                            styles: {
                                clear      : 'both',
                                'float'    : 'left',
                                marginTop  : 20,
                                paddingLeft: 80,
                                width      : '100%'
                            }
                        }).inject(Body, 'after');

                        var Select = new Element('select', {
                            styles: {
                                marginTop: 10,
                                maxWidth : '100%',
                                width    : 300
                            }
                        }).inject(Container);

                        Shipping.getShippingTypes().then(function (result) {
                            for (var i in result) {
                                if (!result.hasOwnProperty(i)) {
                                    continue;
                                }

                                new Element('option', {
                                    value: result[i].name,
                                    html : result[i].title
                                }).inject(Select);
                            }

                            Win.Loader.hide();
                        }).catch(function () {
                            Win.Loader.hide();
                        });
                    },
                    onSubmit: function (Win) {
                        Win.Loader.show();

                        var Select = Win.getContent().getElement('select');

                        Shipping.createShipping(Select.value).then(function (newId) {
                            Win.close();
                            self.refresh();
                            self.openShipping(newId);
                        }).catch(function () {
                            Win.Loader.hide();
                        });
                    }
                }
            }).open();
        },

        /**
         * open the add dialog
         */
        $openDeleteDialog: function () {
            var selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            var self       = this,
                shipping   = selected[0].title,
                shippingId = selected[0].id;

            if (shipping === '') {
                shipping = shippingId;
            }

            new QUIConfirm({
                texticon   : 'fa fa-trash',
                icon       : 'fa fa-trash',
                title      : QUILocale.get(lg, 'window.delete.title'),
                information: QUILocale.get(lg, 'window.delete.information', {
                    shipping: shipping
                }),
                text       : QUILocale.get(lg, 'window.delete.text', {
                    shipping: shipping
                }),
                autoclose  : false,
                maxHeight  : 400,
                maxWidth   : 600,
                ok_button  : {
                    text     : QUILocale.get('quiqqer/system', 'delete'),
                    textimage: 'fa fa-trash'
                },
                events     : {
                    onSubmit: function (Win) {
                        Win.Loader.show();

                        Shipping.deleteShipping(shippingId).then(function () {
                            Win.close();
                            self.refresh();
                        });
                    }
                }
            }).open();
        },

        /**
         * refresh the button disable enable status
         * looks at the grid
         */
        $refreshButtonStatus: function () {
            var selected = this.$Grid.getSelectedIndices(),
                buttons  = this.$Grid.getButtons();

            var Edit = buttons.filter(function (Btn) {
                return Btn.getAttribute('name') === 'edit';
            })[0];

            var Delete = buttons.filter(function (Btn) {
                return Btn.getAttribute('name') === 'delete';
            })[0];

            if (!selected.length) {
                Edit.disable();
                Delete.disable();
                return;
            }

            Edit.enable();
            Delete.enable();
        }
    });
});