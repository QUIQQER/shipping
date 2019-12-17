<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_shippingStatus_delete
 */

use QUI\ERP\Shipping\ShippingStatus\Handler;

/**
 * Delete a shipping status
 *
 * @param int $id
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_shippingStatus_delete',
    function ($id) {
        Handler::getInstance()->deleteShippingStatus($id);
    },
    ['id'],
    'Permission::checkAdminUser'
);
