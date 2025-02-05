/**
 * @module package/quiqqer/shipping/bin/backend/controls/TrackingInput
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/shipping/bin/backend/controls/TrackingInput', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Select',
    'Ajax',

    'css!package/quiqqer/shipping/bin/backend/controls/TrackingInput.css'

], function(QUI, QUIControl, QUISelect, QUIAjax) {
    'use strict';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/shipping/bin/backend/controls/TrackingInput',

        Binds: [
            '$onImport',
            '$onChange'
        ],

        initialize: function(options) {
            this.parent(options);

            this.$value = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        $onImport: function() {
            this.$Value = this.$Elm;
            this.$Value.setStyle('display', 'none');

            this.$Elm = new Element('div').wraps(this.$Value);
            this.$Elm.addClass('quiqqer-shipping-trackingInput');
            this.$Elm.addClass('field-container-field');
            this.$Elm.addClass('field-container-field-no-padding');

            this.$TrackingType = new QUISelect({
                events: {
                    onChange: this.$onChange
                },
                styles: {
                    width: 100
                }
            }).inject(this.$Elm);

            this.$TrackingType.$Menu.getElm().addClass('quiqqer-shipping-trackingInput-contextMenu');

            this.$Input = new Element('input').inject(this.$Elm);
            this.$Input.addClass('quiqqer-shipping-trackingInput-input');
            this.$Input.addEvent('change', this.$onChange);
            this.$Input.addEvent('blur', this.$onChange);

            this.getTrackingList().then((list) => {
                for (let i = 0, len = list.length; i < len; i++) {
                    this.$TrackingType.appendChild(
                        list[i].title,
                        list[i].type,
                        URL_OPT_DIR + list[i].image
                    );
                }

                if (this.$value) {
                    this.setValue(this.$value);
                }
            });
        },

        /**
         * Return the tracking information
         *
         * @returns {Promise}
         */
        getTrackingList: function() {
            return new Promise((resolve) => {
                QUIAjax.get('package_quiqqer_shipping_ajax_backend_tracking_getList', resolve, {
                    'package': 'quiqqer/shipping'
                });
            });
        },

        $onChange: function() {
            const tracking = this.$TrackingType.getValue();
            const trackingNumber = this.$Input.value;

            this.$Value.set('value', JSON.encode({
                type: tracking,
                number: trackingNumber
            }));
        },

        setValue: function(value) {
            if (typeof value === 'string') {
                value = JSON.decode(value);
            }

            this.$value = value;

            if (value && typeof this.$TrackingType.setValue === 'function') {
                this.$TrackingType.setValue(value.type);
            }

            if (value) {
                this.$Input.value = value.number;
                this.$onChange();
            }
        }
    });
});
