<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_delete
 */

use QUI\ERP\Shipping\Types\Factory;

/**
 * Delete the shipping entry
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_delete',
    function ($shippingId) {
        $Factory = new Factory();
        $Factory->getChild($shippingId)->delete();
    },
    ['shippingId'],
    'Permission::checkAdminUser'
);
