<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_shippingStatus_list
 */

use QUI\ERP\Shipping\ShippingStatus\Handler;

/**
 * Returns shipping status list for a grid
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_shippingStatus_list',
    function () {
        $Grid    = new QUI\Utils\Grid();
        $Handler = Handler::getInstance();

        $list   = $Handler->getShippingStatusList();
        $result = \array_map(function ($Status) {
            /* @var $Status \QUI\ERP\Shipping\ShippingStatus\Status */
            return $Status->toArray(QUI::getLocale());
        }, $list);

        \usort($result, function ($a, $b) {
            if ($a['id'] == $b['id']) {
                return 0;
            }

            return $a['id'] > $b['id'] ? 1 : -1;
        });

        return $Grid->parseResult($result, \count($result));
    },
    false,
    'Permission::checkAdminUser'
);
