/**
 * @module package/quiqqer/shipping/bin/backend/utils/ShippingUtils
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/shipping/bin/backend/utils/ShippingUtils', [

    'Locale',
    'qui/controls/buttons/Switch'

], function (QUILocale, QUISwitch) {

    "use strict";

    return {

        /**
         * Return a rule data from ajax for a rule grid
         *
         * @param {Array} rules
         * @return {Array}
         */
        parseRulesDataForGrid: function (rules) {
            var self = this;

            return rules.map(function (entry) {
                return self.parseRuleDataForGrid(entry);
            });
        },

        /**
         * parse one rule entry of an ajax rule array to a rule grid entry
         *
         * @param {Object} ruleData
         * @return {Object}
         */
        parseRuleDataForGrid: function (ruleData) {
            var current = QUILocale.getCurrent();

            ruleData.title        = ruleData.title[current];
            ruleData.workingTitle = ruleData.workingTitle[current];

            ruleData.statusNode = new Element('span', {
                'class': parseInt(ruleData.active) ? 'fa fa-check' : 'fa fa-close',
                styles : {
                    lineHeight: 26
                }
            });

            if (parseInt(ruleData.discount_type) === 0) {
                ruleData.discount_type_text = QUILocale.get(
                    'quiqqer/shipping',
                    'discount.type.abs'
                );
            } else {
                ruleData.discount_type_text = QUILocale.get(
                    'quiqqer/shipping',
                    'discount.type.percentage'
                );
            }

            return ruleData;
        }
    };
});