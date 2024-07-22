/**
 * @module package/quiqqer/shipping/bin/ShippingStatus
 *
 * Main instance of the shipping status handler
 *
 * @require package/quiqqer/shipping/bin/backend/classes/ShippingStatus
 */
define('package/quiqqer/shipping/bin/backend/ShippingStatus', [
    'package/quiqqer/shipping/bin/backend/classes/ShippingStatus'
], function(ShippingStatus) {
    'use strict';
    return new ShippingStatus();
});
