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

        $Rule = $Factory->getChild($ruleId);
        $Rule->setAttributes($data);
        $Rule->update();
    },
    ['ruleId', 'data'],
    'Permission::checkAdminUser'
);
