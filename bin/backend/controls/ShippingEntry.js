/**
 * @module package/quiqqer/shipping/bin/backend/controls/ShippingEntry
 * @author www.pcsg.de (Henning Leutz)
 *
 * Shipping Entry Panel - Eine Versandart im Backend
 */
define('package/quiqqer/shipping/bin/backend/controls/ShippingEntry', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/windows/Confirm',
    'package/quiqqer/shipping/bin/backend/Shipping',
    'package/quiqqer/shipping/bin/backend/controls/ShippingEntry.List',
    'package/quiqqer/translator/bin/Translator',
    'package/quiqqer/translator/bin/controls/Update',
    'qui/utils/Form',
    'Mustache',
    'Locale',

    'text!package/quiqqer/shipping/bin/backend/controls/ShippingEntry.html'

], function (QUI, QUIPanel, QUIConfirm, Shipping, ShippingRulesGrid,
             Translator, TranslatUpdater, FormUtils, Mustache, QUILocale, template) {
    "use strict";

    var lg = 'quiqqer/shipping';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/shipping/bin/backend/controls/ShippingEntry',

        Binds: [
            'showInformation',
            'showDescription',
            'openDeleteDialog',
            'toggleStatus',
            'save',
            '$onCreate',
            '$onRefresh',
            '$showContainer',
            '$hideContainer',
            '$onShippingDelete',
            '$onShippingChange'
        ],

        options: {
            shippingId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                '#id': "shippingId" in options ? options.shippingId : false
            });

            this.$Container        = null;
            this.$IconField        = null;
            this.$DataTitle        = null;
            this.$DataWorkingTitle = null;
            this.$DataDescription  = null;


            this.addEvents({
                onCreate : this.$onCreate,
                onInject : this.$onInject,
                onRefresh: this.$onRefresh,
                onDestroy: this.$onDestroy
            });
        },

        /**
         * event: on create
         */
        $onCreate: function () {
            this.addButton({
                name     : 'save',
                title    : QUILocale.get('quiqqer/core', 'save'),
                text     : QUILocale.get('quiqqer/core', 'save'),
                textimage: 'fa fa-save',
                events   : {
                    onClick: this.save
                }
            });

            this.addButton({
                type: 'separator'
            });

            this.addButton({
                name     : 'status',
                text     : QUILocale.get('quiqqer/core', 'deactivate'),
                title    : QUILocale.get('quiqqer/core', 'deactivate'),
                textimage: 'fa fa-remove',
                disabled : true,
                events   : {
                    onClick: this.toggleStatus
                }
            });

            this.addButton({
                name  : 'delete',
                title : QUILocale.get('quiqqer/system', 'delete'),
                icon  : 'fa fa-trash',
                events: {
                    onClick: this.openDeleteDialog
                },
                styles: {
                    'float': 'right'
                }
            });


            this.addCategory({
                name  : 'information',
                text  : QUILocale.get('quiqqer/system', 'information'),
                icon  : 'fa fa-file-o',
                events: {
                    onClick: this.showInformation
                }
            });

            this.addCategory({
                name  : 'description',
                text  : QUILocale.get('quiqqer/system', 'description'),
                icon  : 'fa fa-file-text-o',
                events: {
                    onClick: this.showDescription
                }
            });

            this.$Container = new Element('div', {
                styles: {
                    height  : '100%',
                    overflow: 'auto',
                    padding : 10,
                    position: 'relative',
                    width   : '100%'
                }
            }).inject(this.getContent());

            this.getContent().setStyles({
                padding : 0,
                position: 'relative'
            });
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            var self = this;

            this.Loader.show();

            Shipping.addEvents({
                onShippingDeactivate: this.$onShippingChange,
                onShippingActivate  : this.$onShippingChange,
                onShippingDelete    : this.$onShippingDelete,
                onShippingUpdate    : this.$onShippingChange
            });

            this.reload().then(function () {
                self.getCategory('information').click();
            });
        },

        /**
         * event: on refresh
         */
        $onRefresh: function () {
            var data = this.getAttribute('data');

            if (!data || !("active" in data)) {
                return;
            }

            var status = parseInt(data.active),
                Status = this.getButtons('status');

            if (status) {
                Status.setAttribute('text', QUILocale.get('quiqqer/core', 'deactivate'));
                Status.setAttribute('title', QUILocale.get('quiqqer/core', 'deactivate'));
                Status.setAttribute('textimage', 'fa fa-remove');
            } else {
                Status.setAttribute('text', QUILocale.get('quiqqer/core', 'activate'));
                Status.setAttribute('title', QUILocale.get('quiqqer/core', 'activate'));
                Status.setAttribute('textimage', 'fa fa-check');
            }

            Status.enable();
        },

        /**
         * event: on destroy
         */
        $onDestroy: function () {
            Shipping.removeEvents({
                onShippingDeactivate: this.$onShippingChange,
                onShippingActivate  : this.$onShippingChange,
                onShippingDelete    : this.$onShippingDelete,
                onShippingUpdate    : this.$onShippingChange
            });
        },

        /**
         * event: on shipping change
         *
         * @param {Object} Shipping
         * @param {Number} shippingId
         * @param {Object} data
         */
        $onShippingChange: function (Shipping, shippingId, data) {
            if (shippingId !== this.getAttribute('shippingId')) {
                return;
            }

            this.setAttribute('data', data);
            this.refresh();
        },

        /**
         * event: on shipping change
         *
         * @param {Object} Shipping
         * @param {Number} shippingId
         */
        $onShippingDelete: function (Shipping, shippingId) {
            if (shippingId === this.getAttribute('shippingId')) {
                this.destroy();
            }
        },

        /**
         * Reload the shipping data
         */
        reload: function () {
            var self       = this,
                shippingId = this.getAttribute('shippingId');

            return Shipping.getShippingEntry(shippingId).then(function (result) {
                var current = QUILocale.getCurrent();

                self.setAttribute('title', result.title[current]);
                self.setAttribute('icon', 'fa fa-credit-card-alt');

                delete result.title;
                delete result.workingTitle;
                delete result.description;

                self.setAttribute('data', result);
                self.refresh();
            }).catch(function (err) {
                console.error(err);
                self.destroy();
            });
        },

        /**
         * Save the current shipping settings
         *
         * @return {Promise}
         */
        save: function () {
            var self       = this,
                shippingId = this.getAttribute('shippingId');

            this.Loader.show();
            this.$unloadContainerData();

            this.$__storageData = {};
            this.$__running     = false;

            var data = this.getAttribute('data');

            return new Promise(function (resolve, reject) {
                Shipping.updateShipping(shippingId, data).then(function () {
                    return self.reload();
                }).then(function () {
                    resolve();
                    self.Loader.hide();
                }).catch(function (exc) {
                    self.Loader.hide();
                    reject(exc);
                });
            });
        },

        /**
         * Activate the shipping
         */
        activate: function () {
            var self       = this,
                shippingId = this.getAttribute('shippingId');

            self.Loader.show();

            Shipping.activateShipping(shippingId).then(function (data) {
                self.setAttribute('data', data);
                self.refresh();
                self.Loader.hide();
            });
        },

        /**
         * Deactivate the shipping
         */
        deactivate: function () {
            var self       = this,
                shippingId = this.getAttribute('shippingId');

            Shipping.deactivateShipping(shippingId).then(function (data) {
                self.setAttribute('data', data);
                self.refresh();
                self.Loader.hide();
            });
        },

        /**
         * Toggle the active status of the shipping
         */
        toggleStatus: function () {
            var data = this.getAttribute('data');

            if (!("active" in data)) {
                return;
            }

            if (parseInt(data.active)) {
                return this.deactivate();
            }

            return this.activate();
        },

        /**
         * Show the information
         */
        showInformation: function () {
            var self = this,
                data = self.getAttribute('data');

            this.$hideContainer().then(function (Container) {
                Container.set({
                    html: Mustache.render(template, {
                        header              : QUILocale.get(lg, 'shipping.edit.template.title'),
                        id                  : QUILocale.get(lg, 'shipping.edit.template.id'),
                        type                : QUILocale.get(lg, 'shipping.edit.template.type'),
                        title               : QUILocale.get(lg, 'shipping.edit.template.title'),
                        workingTitle        : QUILocale.get('quiqqer/system', 'workingtitle'),
                        usageHeader         : QUILocale.get(lg, 'shipping.edit.template.usage'),
                        usageFrom           : QUILocale.get(lg, 'shipping.edit.template.usage.from'),
                        usageTo             : QUILocale.get(lg, 'shipping.edit.template.usage.to'),
                        usageAmountOf       : QUILocale.get(lg, 'shipping.edit.template.shopping.amount.of'),
                        usageAmountTo       : QUILocale.get(lg, 'shipping.edit.template.shopping.amount.to'),
                        usageValueOf        : QUILocale.get(lg, 'shipping.edit.template.purchase.value.of'),
                        usageValueTo        : QUILocale.get(lg, 'shipping.edit.template.purchase.value.to'),
                        usageAssignment     : QUILocale.get(lg, 'shipping.edit.template.assignment'),
                        usageAssignmentAreas: QUILocale.get(lg, 'shipping.edit.template.areas'),
                        calculationPriority : QUILocale.get(lg, 'shipping.edit.template.calculationPriority'),
                        customIcon          : QUILocale.get(lg, 'shipping.edit.template.customIcon'),
                        customIconDesc      : QUILocale.get(lg, 'shipping.edit.template.customIcon.description'),

                        shippingRulesHeader: QUILocale.get(lg, 'shipping.edit.template.shippingRules.description'),

                        usageAssignmentProduct : QUILocale.get(lg, 'shipping.edit.template.assignment.product'),
                        usageAssignmentCategory: QUILocale.get(lg, 'shipping.edit.template.assignment.category'),
                        usageAssignmentUser    : QUILocale.get(lg, 'shipping.edit.template.assignment.user'),

                        paymentHeader: QUILocale.get(lg, 'shipping.edit.template.assignment.payment.header'),
                        paymentUsage : QUILocale.get(lg, 'shipping.edit.template.assignment.payment.usage')
                    })
                });

                Container.getElement('.field-id').set('html', data.id);

                new ShippingRulesGrid({
                    shippingId: self.getAttribute('shippingId'),
                    name      : 'shipping_rules',
                    events    : {
                        onRefreshBegin: function () {
                            self.Loader.show();
                        },

                        onRefreshEnd: function () {
                            self.Loader.hide();
                        }
                    }
                }).inject(
                    Container.getElement('.shipping-rules tbody td')
                );

                if (typeof data.shippingType !== 'undefined' &&
                    typeof data.shippingType.title !== 'undefined') {
                    Container.getElement('.field-type').set('html', data.shippingType.title);
                }

                FormUtils.setDataToForm(data, Container.getElement('form'));

                return Promise.all([
                    self.$getTranslationData('title'),
                    self.$getTranslationData('workingTitle')
                ]);
            }).then(function (translationData) {
                return new Promise(function (resolve, reject) {
                    require(['controls/lang/InputMultiLang'], function (InputMultiLang) {
                        self.$DataTitle        = new InputMultiLang().replaces(self.$Container.getElement('.shipping-title'));
                        self.$DataWorkingTitle = new InputMultiLang().replaces(self.$Container.getElement('.shipping-workingTitle'));

                        self.$DataTitle.setData(translationData[0]);
                        self.$DataWorkingTitle.setData(translationData[1]);

                        resolve();
                    }, reject);
                });
            }).then(function () {
                return self.$showContainer();
            }).then(function () {
                self.$IconField = QUI.Controls.getById(
                    self.$Container.getElement('[name="icon"]').get('data-quiid')
                );

                self.$IconField.setValue(data.icon_path);
            }).catch(function (err) {
                console.error(err);
            });
        },

        /**
         * SHow the description
         */
        showDescription: function () {
            var self = this;

            Promise.all([
                this.$hideContainer(),
                this.$getTranslationData('description')
            ]).then(function (result) {
                var Container   = result[0],
                    description = result[1];

                return new Promise(function (resolve) {
                    require(['controls/lang/ContentMultiLang'], function (ContentMultiLang) {
                        self.$DataDescription = new ContentMultiLang({
                            styles: {
                                height: '100%'
                            },
                            events: {
                                onLoad: function () {
                                    self.$DataDescription.setData(description);
                                    resolve();
                                }
                            }
                        }).inject(Container);
                    });
                });
            }).then(function () {
                return self.$showContainer();
            });
        },

        /**
         * Opens the delete dialog
         */
        openDeleteDialog: function () {
            var self       = this,
                shippingId = this.getAttribute('shippingId');

            new QUIConfirm({
                texticon   : 'fa fa-trash',
                icon       : 'fa fa-trash',
                title      : QUILocale.get(lg, 'window.delete.title'),
                information: QUILocale.get(lg, 'window.delete.information', {
                    shipping: this.getAttribute('title')
                }),
                text       : QUILocale.get(lg, 'window.delete.text', {
                    shipping: this.getAttribute('title')
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
                            self.destroy();
                        });
                    }
                }
            }).open();
        },

        // region UI Helper

        /**
         * Show the container
         *
         * @return {Promise}
         */
        $showContainer: function () {
            var self = this;

            return new Promise(function (resolve) {
                QUI.parse(self.$Container).then(function () {
                    moofx(self.$Container).animate({
                        opacity: 1,
                        top    : 0
                    }, {
                        duration: 250,
                        callback: function () {
                            self.Loader.hide();
                            resolve(self.$Container);
                        }
                    });
                });
            });
        },

        /**
         * Hide the container
         *
         * @return {Promise}
         */
        $hideContainer: function () {
            var self = this;

            this.Loader.show();

            return new Promise(function (resolve) {
                moofx(self.$Container).animate({
                    opacity: 0,
                    top    : -20
                }, {
                    duration: 250,
                    callback: function () {
                        self.$unloadContainer();
                        self.$Container.set('html', '');
                        resolve(self.$Container);
                    }
                });
            });
        },

        /**
         * unload the data from the current category
         */
        $unloadContainer: function () {
            this.$unloadContainerData();

            if (this.$DataDescription) {
                this.$DataDescription.destroy();
                this.$DataDescription = null;
            }

            if (this.$DataTitle) {
                this.$DataTitle.destroy();
                this.$DataTitle = null;
            }

            if (this.$DataWorkingTitle) {
                this.$DataWorkingTitle.destroy();
                this.$DataWorkingTitle = null;
            }

            if (this.$IconField) {
                this.$IconField.destroy();
                this.$IconField = null;
            }
        },

        /**
         * Unload the current container data and set the data to the shipping object
         * The data is not saved
         */
        $unloadContainerData: function () {
            var Form = this.$Container.getElement('form');

            if (this.$DataDescription) {
                this.$setData('description', this.$DataDescription.getData());
            }

            if (this.$DataTitle) {
                this.$setData('title', this.$DataTitle.getData());
            }

            if (this.$DataWorkingTitle) {
                this.$setData('workingTitle', this.$DataWorkingTitle.getData());
            }

            if (this.$IconField) {
                this.$setData('icon', this.$IconField.getValue());
            }

            if (Form) {
                var formData = FormUtils.getFormData(Form);

                for (var key in formData) {
                    if (formData.hasOwnProperty(key)) {
                        this.$setData(key, formData[key]);
                    }
                }
            }
        },

        //endregion

        /**
         * set the shipping data for the panel
         *
         * @param {String} name
         * @param {String} value
         */
        $setData: function (name, value) {
            var data   = this.getAttribute('data');
            data[name] = value;

            this.setAttribute('data', data);
        },

        /**
         * Return a data entry
         *
         * @param {String} name
         */
        $getData: function (name) {
            var data = this.getAttribute('data');

            if (name in data) {
                return data[name];
            }

            return false;
        },

        /**
         * Return the current data of a language field
         *
         * @param name
         * @return {Promise}
         */
        $getTranslationData: function (name) {
            var shippingId = this.getAttribute('shippingId');

            var title        = 'shipping.' + shippingId + '.title';
            var description  = 'shipping.' + shippingId + '.description';
            var workingTitle = 'shipping.' + shippingId + '.workingTitle';

            if (typeof this.$__running === 'undefined') {
                this.$__storageData = {};
                this.$__running     = false;
            }

            var getData = function () {
                return new Promise(function (resolve, reject) {
                    if ("title" in this.$__storageData) {
                        resolve();
                        return;
                    }

                    if (this.$__running) {
                        (function () {
                            getData().then(resolve, reject);
                        }).delay(100);
                        return;
                    }

                    this.$__running = true;

                    Promise.all([
                        Translator.get(lg, title, lg),
                        Translator.get(lg, description, lg),
                        Translator.get(lg, workingTitle, lg),
                        Translator.getAvailableLanguages()
                    ]).then(function (promiseResult) {
                        this.$__storageData.title        = promiseResult[0];
                        this.$__storageData.description  = promiseResult[1];
                        this.$__storageData.workingTitle = promiseResult[2];
                        this.$__storageData.languages    = promiseResult[3];

                        this.$__running = false;
                        resolve();
                    }.bind(this), reject);
                }.bind(this));
            }.bind(this);


            return getData().then(function () {
                var data = this.$__storageData;

                var result = {};
                var value  = data[name];

                if (this.$getData(name)) {
                    value = this.$getData(name);
                }

                data.languages.each(function (language) {
                    var val = value[language];

                    if (value[language + '_edit'] !== '' && value[language + '_edit']) {
                        val = value[language + '_edit'];
                    }

                    result[language] = val;
                });

                return result;
            }.bind(this));
        }
    });
});
