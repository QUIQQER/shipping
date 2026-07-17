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
QUI::getAjax()->registerFunction(
    'package_quiqqer_shipping_ajax_backend_delete',
    function ($shippingId): void {
        $Factory = new Factory();
        $Factory->getChild($shippingId)->delete();
    },
    ['shippingId'],
    'Permission::checkAdminUser'
);
