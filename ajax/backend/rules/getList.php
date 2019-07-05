<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_getShippingList
 */

use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Shipping\Shipping;
use QUI\ERP\Shipping\Types\ShippingEntry;

/**
 * Return all active shipping entries
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_rules_getList',
    function () {
        $rules  = QUI\ERP\Shipping\Rules\Factory::getInstance()->getChildren();
        $result = [];

        foreach ($rules as $Rule) {
            /* @var $Rule \QUI\ERP\Shipping\Rules\ShippingRule */
            $result[] = $Rule->toArray();
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
