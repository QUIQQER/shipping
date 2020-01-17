<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_shippingStatus_get
 */

use QUI\ERP\Shipping\ShippingStatus\Handler;

/**
 * Create a new shipping status
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_shippingStatus_get',
    function ($id) {
        return Handler::getInstance()->getShippingStatus($id)->toArray();
    },
    ['id'],
    'Permission::checkAdminUser'
);
