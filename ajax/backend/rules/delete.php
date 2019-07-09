<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_rules_delete
 */

use QUI\ERP\Shipping\Rules\Factory;
use QUI\ExceptionStack;

/**
 * Delete the shipping rule(s)
 *
 * @throws QUI\ExceptionStack
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_rules_delete',
    function ($ruleIds) {
        $ruleIds = \json_decode($ruleIds, true);
        $Factory = new Factory();

        if (!\is_array($ruleIds)) {
            $ruleIds = [$ruleIds];
        }

        $EStack = new QUI\ExceptionStack();

        foreach ($ruleIds as $ruleId) {
            try {
                $Factory->getChild($ruleId)->delete();
            } catch (QUI\Exception $Exception) {
                $EStack->addException($Exception);
            }
        }

        if (!$EStack->isEmpty()) {
            throw $EStack;
        }
    },
    ['ruleIds'],
    'Permission::checkAdminUser'
);
