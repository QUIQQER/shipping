/**
 * @module package/quiqqer/shipping/bin/backend/utils/ShippingUtils
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/shipping/bin/backend/utils/ShippingUtils', [

    'Locale'

], function (QUILocale) {

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

            return ruleData;
        }
    };
});