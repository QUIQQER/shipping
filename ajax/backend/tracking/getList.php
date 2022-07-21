<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_tracking_getList
 */

use QUI\ERP\Shipping\Tracking;

QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_tracking_getList',
    function () {
        return Tracking\Tracking::getActiveCarriers();
    },
    false,
    'Permission::checkAdminUser'
);
