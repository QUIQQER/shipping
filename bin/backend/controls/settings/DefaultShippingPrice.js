/**
 * Price input that finishes validation before the settings panel saves its configuration.
 */
define('package/quiqqer/shipping/bin/backend/controls/settings/DefaultShippingPrice', [
    'qui/QUI',
    'package/quiqqer/erp/bin/backend/controls/elements/PriceCalcInput'
], function(QUI, PriceCalcInput) {
    'use strict';

    return new Class({
        Extends: PriceCalcInput,
        Type: 'package/quiqqer/shipping/bin/backend/controls/settings/DefaultShippingPrice',

        save: async function() {
            const Editor = Array.from(this.$Input.form.elements).find((Field) => {
                return this.getElm().contains(Field) && Field.type === 'text' && !Field.disabled;
            });

            if (Editor.value === '') {
                this.$Input.value = '';
            } else if (this.isNetto()) {
                await this.setNettoByUser(Editor.value);
            } else {
                await this.setBruttoByUser(Editor.value);
            }

            const Panel = QUI.Controls.getByType('controls/desktop/panels/XML').find((Settings) => {
                return Settings.getElm().contains(this.getElm());
            });

            // XML panels collect form values before awaiting controls with a save method.
            Panel.unloadCategory(false);
        }
    });
});
