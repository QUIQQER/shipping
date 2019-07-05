<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_getShippingList
 */

use QUI\ERP\Shipping\Shipping;
use QUI\ERP\Shipping\Types\ShippingEntry;

/**
 * Return all active shipping entries
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_rules_getList',
    function () {

        return [];
    },
    false,
    'Permission::checkAdminUser'
);
