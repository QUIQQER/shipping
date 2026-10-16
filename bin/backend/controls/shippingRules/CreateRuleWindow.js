/**
 * @module package/quiqqer/shipping/bin/backend/controls/shippingRules/CreateRuleWindow
 * @author www.pcsg.de (Henning Leutz
 */
define('package/quiqqer/shipping/bin/backend/controls/shippingRules/CreateRuleWindow', [

    'qui/QUI',
    'qui/controls/windows/Confirm',
    'package/quiqqer/shipping/bin/backend/controls/shippingRules/CreateRule',
    'Locale'

], function(QUI, QUIConfirm, CreateRule, QUILocale) {
    'use strict';

    return new Class({

        Extends: QUIConfirm,
        Type: 'package/quiqqer/shipping/bin/backend/controls/shippingRules/CreateRuleWindow',

        Binds: [
            '$onOpen',
            '$onSubmit'
        ],

        options: {
            maxHeight: 800,
            maxWidth: 600,
            autoclose: false
        },

        initialize: function(options) {
            this.parent(options);

            this.setAttributes({
                title: QUILocale.get('quiqqer/shipping', 'window.shipping.rules.title'),
                icon: 'fa fa-truck',
                ok_button: {
                    text: QUILocale.get('quiqqer/shipping', 'window.shipping.entry.delete.rule.create'),
                    textimage: 'fa fa-plus'
                }
            });

            this.$Create = null;
            this.$submitting = false;

            this.addEvents({
                onOpen: this.$onOpen,
                onSubmit: this.$onSubmit
            });
        },

        /**
         * event: on inject
         */
        $onOpen: function() {
            const self = this;

            this.Loader.show();
            this.getContent().set('html', '');

            this.$Create = new CreateRule({
                events: {
                    onLoad: function() {
                        self.Loader.hide();
                    }
                }
            }).inject(this.getContent());
        },

        /**
         * event: on submit
         */
        $onSubmit: function() {
            if (this.$submitting) {
                return;
            }

            this.$submitting = true;
            this.Loader.show();

            return this.$Create.submit().finally(() => {
                this.$submitting = false;
                this.Loader.hide();
            }).then(() => {
                this.close();
            });
        }
    });
});
