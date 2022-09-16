<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_articleList_getShippingPriceFactor
 */

use QUI\ERP\Shipping\Shipping;

/**
 * Return the shipping price for a article list
 * - all shipping rules are summarized
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_articleList_getShippingPriceFactor',
    function ($shippingId) {
        $Shipping = Shipping::getInstance()->getShippingEntry($shippingId);
        
        return [
            'title' => $Shipping->getTitle(),
            'price' => $Shipping->getPrice()
        ];
    },
    ['shippingId'],
    'Permission::checkAdminUser'
);
