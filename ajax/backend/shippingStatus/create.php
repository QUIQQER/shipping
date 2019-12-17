<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_shippingStatus_create
 */

use QUI\ERP\Shipping\ShippingStatus\Factory;
use QUI\ERP\Shipping\ShippingStatus\Handler;
use QUI\Utils\Security\Orthos;

/**
 * Create a new  shipping status
 *
 * @param int $id - ShippingStatus ID
 * @param string $color - hex color code
 * @param array $title - (multilignual) titel
 * @param bool $notification - send auto-notification on status change
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_shippingStatus_create',
    function ($id, $color, $title, $notification) {
        $id = (int)$id;

        Factory::getInstance()->createShippingStatus(
            $id,
            Orthos::clear($color),
            Orthos::clearArray(\json_decode($title, true))
        );

        Handler::getInstance()->setShippingStatusNotification($id, \boolval($notification));
    },
    ['id', 'color', 'title', 'notification'],
    'Permission::checkAdminUser'
);
