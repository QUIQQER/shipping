<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_activate
 */

use QUI\ERP\Shipping\Types\Factory;

/**
 * Activate a shipping
 *
 * @param integer $shippingId
 * @return array
 */

QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_activate',
    function ($shippingId) {
        $Shipping = new Factory();
        $ShippingEntry = $Shipping->getChild($shippingId);
        $ShippingEntry->activate();

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/shipping',
                'message.shipping.activate.successfully',
                ['shipping' => $ShippingEntry->getTitle()]
            )
        );

        return $ShippingEntry->toArray();
    },
    ['shippingId'],
    'Permission::checkAdminUser'
);
