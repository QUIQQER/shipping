<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_shippingStatus_getNotificationText
 */

use QUI\ERP\Order\Handler as OrderHandler;
use QUI\ERP\Shipping\ShippingStatus\Handler;

/**
 * Get status change notification text for a specific order
 *
 * @param int $id - Shipping Status ID
 * @param int $orderId - Order ID
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_shippingStatus_getNotificationText',
    function ($shippingId, $orderId) {
        try {
            $Order = OrderHandler::getInstance()->get($orderId);

            return Handler::getInstance()
                ->getShippingStatus($shippingId)
                ->getStatusChangeNotificationText($Order);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return '';
        }
    },
    ['shippingId', 'orderId'],
    'Permission::checkAdminUser'
);
