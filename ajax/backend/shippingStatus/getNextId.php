<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_shippingStatus_getNextId
 */

use QUI\ERP\Shipping\ShippingStatus\Factory;

/**
 * Return next available ID
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_shippingStatus_getNextId',
    function () {
        return Factory::getInstance()->getNextId();
    },
    false,
    'Permission::checkAdminUser'
);
