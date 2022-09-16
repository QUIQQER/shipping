require(['qui/QUI'], function (QUI) {
    "use strict";

    function getShippingPrice(shippingId) {
        return new Promise(function (resolve) {
            require(['Ajax'], function (QUIAjax) {
                QUIAjax.get('package_quiqqer_shipping_ajax_backend_articleList_getShippingPriceFactor', resolve, {
                    'package' : 'quiqqer/shipping',
                    shippingId: shippingId
                });
            });
        });
    }

    QUI.addEvent('quiqqerErpPriceFactorWindow', function (PriceFactorWindow) {
        const Content = PriceFactorWindow.getContent();
        const Buttons = Content.getElement('.quiqqer-erp-priceFactors-button');
        const ArticleList = PriceFactorWindow.getArticleList();

        require(['Locale'], function (QUILocale) {
            new Element('button', {
                'class': 'qui-button',
                html   : '<span class="fa fa-truck"></span>',
                title  : QUILocale.get('quiqqer/shipping', 'add.shipping.priceFactor'),
                styles : {
                    'float'    : 'right',
                    marginRight: '10px'
                },
                events : {
                    click: function (e) {
                        e.stop();

                        require([
                            'package/quiqqer/shipping/bin/backend/controls/ShippingWindow'
                        ], function (ShippingWindow) {
                            new ShippingWindow({
                                events: {
                                    onSubmit: function (Instance, value) {
                                        PriceFactorWindow.Loader.show();

                                        const currency = ArticleList.getAttribute('currency');
                                        const vat = 19;
                                        let shippingData;

                                        getShippingPrice(value[0].id).then(function (result) {
                                            shippingData = result;

                                            return PriceFactorWindow.getPriceFactorData(
                                                result.price,
                                                vat,
                                                currency
                                            );
                                        }).then((data) => {
                                            let priceFactor = {
                                                calculation      : 2,
                                                calculation_basis: 2,
                                                description      : shippingData.title,
                                                identifier       : "",
                                                index            : ArticleList.countPriceFactors(),
                                                nettoSum         : data.nettoSum,
                                                nettoSumFormatted: data.nettoSumFormatted,
                                                sum              : data.sum,
                                                sumFormatted     : data.sumFormatted,
                                                title            : shippingData.title,
                                                value            : data.sum,
                                                valueText        : data.valueText,
                                                vat              : vat,
                                                visible          : 1
                                            };

                                            ArticleList.addPriceFactor(priceFactor);
                                            PriceFactorWindow.refresh();
                                        });
                                    }
                                }
                            }).open();
                        });
                    }
                }
            }).inject(Buttons);
        });
    });
});
