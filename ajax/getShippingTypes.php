<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_getShippingTypes
 */

use QUI\ERP\Shipping\Shipping;

/**
 * Return all active shipping
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_getShippingTypes',
    function () {
        return \array_map(function ($Shipping) {
            /* @var $Shipping \QUI\ERP\Shipping\Methods\Free\ShippingType */
            return $Shipping->toArray(QUI::getLocale());
        }, Shipping::getInstance()->getShippingTypes());
    },
    false,
    'Permission::checkAdminUser'
);
