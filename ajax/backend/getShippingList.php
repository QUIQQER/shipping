<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_getShippingList
 */

use QUI\ERP\Shipping\Shipping;
use QUI\ERP\Shipping\Types\ShippingEntry;

/**
 * Return all active shipping entries
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_getShippingList',
    function () {
        $shippingEntries = Shipping::getInstance()->getShippingList();
        $result          = [];

        foreach ($shippingEntries as $ShippingEntry) {
            /* @var $ShippingEntry ShippingEntry */
            $result[] = $ShippingEntry->toArray();
        }

        $current = QUI::getLocale()->getCurrent();

        \usort($result, function ($a, $b) use ($current) {
            $aTitle = $a['title'][$current];
            $bTitle = $b['title'][$current];

            if (!empty($a['workingTitle'][$current])) {
                $aTitle = $a['workingTitle'][$current];
            }

            if (!empty($b['workingTitle'][$current])) {
                $bTitle = $b['workingTitle'][$current];
            }

            return \strcmp($aTitle, $bTitle);
        });

        return $result;
    },
    false,
    'Permission::checkAdminUser'
);
