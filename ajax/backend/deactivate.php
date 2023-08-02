<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_deactivate
 */

use QUI\ERP\Shipping\Types\Factory;

/**
 * Deactivate a shipping
 *
 * @param integer $shippingId
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_deactivate',
    function ($shippingId) {
        $factory = new Factory();
        $Shipping = $factory->getChild($shippingId);
        $Shipping->deactivate();

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/shipping',
                'message.shipping.deactivate.successfully',
                ['shipping' => $Shipping->getTitle()]
            )
        );

        return $Shipping->toArray();
    },
    ['shippingId'],
    'Permission::checkAdminUser'
);
