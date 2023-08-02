<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_create
 */

use QUI\ERP\Shipping\Rules\Factory;

/**
 * Create a new shipping method
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_rules_create',
    function ($rules) {
        $rules = json_decode($rules, true);
        $Factory = new Factory();
        $Rule = $Factory->createChild($rules);

        return $Rule->getId();
    },
    ['rules'],
    'Permission::checkAdminUser'
);
