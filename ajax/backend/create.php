<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_create
 */

use QUI\ERP\Shipping\Shipping;
use QUI\ERP\Shipping\Types\Factory;

/**
 * Create a new shipping method
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_create',
    function ($shippingType) {
        $Type = Shipping::getInstance()->getShippingType($shippingType);

        $Factory = new Factory();
        $Shipping = $Factory->createChild([
            'shipping_type' => $Type->getType()
        ]);

        return $Shipping->getId();
    },
    ['shippingType'],
    'Permission::checkAdminUser'
);
