<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_rules_getRules
 */

/**
 * Return all wanted shipping rules
 *
 * @return array
 */

use QUI\System\Log;

QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_rules_getRules',
    function ($ruleIds) {
        $ruleIds = json_decode($ruleIds, true);
        $Rules = QUI\ERP\Shipping\Rules\Factory::getInstance();

        $result = [];

        if (!is_array($ruleIds)) {
            $ruleIds = [];
        }

        foreach ($ruleIds as $ruleId) {
            try {
                $result[] = $Rules->getChild($ruleId)->toArray();
            } catch (QUI\Exception $Exception) {
                Log::addDebug($Exception);
            }
        }

        // sort by priority
        usort($result, function ($a, $b) {
            $priorityA = $a['priority'] ?? 0;
            $priorityB = $b['priority'] ?? 0;

            if ($priorityA === $priorityB) {
                return $a['id'] <=> $b['id'];
            }

            return $priorityB <=> $priorityA;
        });

        return $result;
    },
    ['ruleIds'],
    'Permission::checkAdminUser'
);
