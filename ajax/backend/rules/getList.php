<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_getShippingList
 */

/**
 * Return all active shipping entries
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_rules_getList',
    function () {
        $rules = QUI\ERP\Shipping\Rules\Factory::getInstance()->getChildren([
            'order' => 'id'
        ]);

        $result = [];

        foreach ($rules as $Rule) {
            /* @var $Rule \QUI\ERP\Shipping\Rules\ShippingRule */
            $result[] = $Rule->toArray();
        }

        return $result;
    },
    false,
    'Permission::checkAdminUser'
);
