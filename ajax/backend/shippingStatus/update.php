<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_shippingStatus_update
 */

use QUI\ERP\Shipping\ShippingStatus\Handler;
use QUI\Utils\Security\Orthos;

/**
 * Update a shipping status
 *
 * @param int $id - Shipping Status ID
 * @param string $color - hex color code
 * @param array $title - (multilingual) title
 * @param bool $notification - send auto-notification on status change
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_shippingStatus_update',
    function ($id, $color, $title, $notification) {
        $id = (int)$id;
        $Handler = Handler::getInstance();

        $Handler->updateShippingStatus(
            $id,
            Orthos::clear($color),
            Orthos::clearArray(\json_decode($title, true))
        );

        $Handler->setShippingStatusNotification($id, \boolval($notification));
    },
    ['id', 'color', 'title', 'notification'],
    'Permission::checkAdminUser'
);
