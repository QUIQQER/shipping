/**
 * @module package/quiqqer/shipping/bin/backend/controls/ShippingWindow
 * @author www.pcsg.de
 */
define('package/quiqqer/shipping/bin/backend/controls/ShippingWindow', [

    'qui/QUI',
    'qui/controls/windows/Confirm',
    'package/quiqqer/shipping/bin/backend/Shipping',
    'controls/grid/Grid',
    'Locale'

], function (QUI, QUIConfirm, Shipping, Grid, QUILocale) {
    "use strict";

    const lg = 'quiqqer/shipping';
    const current = QUILocale.getCurrent();

    return new Class({

        Extends: QUIConfirm,
        Type   : 'package/quiqqer/shipping/bin/backend/controls/ShippingWindow',

        Binds: [
            '$onOpen',
            '$onDblClick',
            'refresh'
        ],

        options: {
            Order: null
        },

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                title    : QUILocale.get(lg, 'shipping.window.title'),
                icon     : 'fa fa-truck',
                maxHeight: 560,
                maxWidth : 500,
            });

            this.$Grid = null;

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        refresh: function () {
            if (!this.$Elm) {
                return;
            }

            this.Loader.show();

            Shipping.getShippingList().then((result) => {
                const toggle = function (Btn) {
                    let data       = Btn.getAttribute('data'),
                        shippingId = data.id,
                        status     = parseInt(data.active);

                    Btn.setAttribute('icon', 'fa fa-spinner fa-spin');

                    if (status) {
                        Shipping.deactivateShipping(shippingId);
                        return;
                    }

                    Shipping.activateShipping(shippingId);
                };

                for (let i = 0, len = result.length; i < len; i++) {
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

                    if (typeOf(result[i].title) !== 'string') {
                        result[i].title = result[i].title[current];
                    }

                    if (typeOf(result[i].workingTitle) !== 'string') {
                        result[i].workingTitle = result[i].workingTitle[current];
                    }

                    if ("shippingType" in result[i] && result[i].shippingType) {
                        result[i].shippingType_display = result[i].shippingType.title;
                    }
                }

                this.$Grid.setData({
                    data: result
                });

                this.$Grid.setHeight(
                    this.getContent().getSize().y - 40
                ).then(() => {
                    this.Loader.hide();
                });
            });
        },

        $onOpen: function () {
            this.Loader.show();
            this.getContent().set('html', '');

            const Container = new Element('div', {
                styles: {
                    height: '100%',
                    width : '100%'
                }
            }).inject(this.getContent());

            this.$Grid = new Grid(Container, {
                columnModel: [
                    {
                        header   : QUILocale.get('quiqqer/system', 'priority'),
                        dataIndex: 'priority',
                        dataType : 'number',
                        width    : 50
                    },
                    {
                        header   : QUILocale.get('quiqqer/system', 'status'),
                        dataIndex: 'status',
                        dataType : 'button',
                        width    : 60
                    },
                    {
                        header   : QUILocale.get('quiqqer/system', 'title'),
                        dataIndex: 'title',
                        dataType : 'string',
                        width    : 200
                    },
                    {
                        header   : QUILocale.get('quiqqer/system', 'workingtitle'),
                        dataIndex: 'workingTitle',
                        dataType : 'string',
                        width    : 200
                    },
                    {
                        header   : QUILocale.get('quiqqer/system', 'id'),
                        dataIndex: 'id',
                        dataType : 'number',
                        width    : 30
                    },
                    {
                        header   : QUILocale.get(lg, 'shipping.type'),
                        dataIndex: 'shippingType_display',
                        dataType : 'string',
                        width    : 200
                    }
                ]
            });

            this.$Grid.addEvents({
                onRefresh : this.refresh,
                onDblClick: this.$onDblClick
            });

            this.refresh();
        },

        $onDblClick: function () {
            this.submit();
        },

        submit: function () {
            const data = this.$Grid.getSelectedData();

            if (!data.length) {
                return;
            }

            this.fireEvent('submit', [
                this,
                data
            ]);

            if (this.getAttribute('autoclose')) {
                this.close();
            }
        }
    });
});