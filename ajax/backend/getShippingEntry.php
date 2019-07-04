<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_getShippingEntry
 */

use QUI\ERP\Shipping\Shipping;

/**
 * Return all active shipping
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_getShippingEntry',
    function ($shippingId) {
        return Shipping::getInstance()->getShippingEntry($shippingId)->toArray();
    },
    ['shippingId'],
    'Permission::checkAdminUser'
);
