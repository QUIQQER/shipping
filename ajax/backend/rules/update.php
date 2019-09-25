<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_rules_update
 */

use \QUI\ERP\Shipping\Rules\Factory;

/**
 * Update a shipping method
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_rules_update',
    function ($ruleId, $data) {
        $data    = \json_decode($data, true);
        $Factory = new Factory();

        /* @var $Rule QUI\ERP\Shipping\Rules\ShippingRule */
        $Rule = $Factory->getChild($ruleId);
        $Rule->setAttributes($data);

        if (isset($data['title'])) {
            $Rule->setTitle($data['title']);
        }

        if (isset($data['workingTitle'])) {
            $Rule->setWorkingTitle($data['workingTitle']);
        }

        $Rule->update();
    },
    ['ruleId', 'data'],
    'Permission::checkAdminUser'
);
