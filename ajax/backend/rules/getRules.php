<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_rules_getRules
 */

/**
 * Return all wanted shipping rules
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_rules_getRules',
    function ($ruleIds) {
        $ruleIds = \json_decode($ruleIds, true);
        $Rules   = QUI\ERP\Shipping\Rules\Factory::getInstance();

        $result = [];

        if (!\is_array($ruleIds)) {
            $ruleIds = [];
        }

        foreach ($ruleIds as $ruleId) {
            try {
                $result[] = $Rules->getChild($ruleId)->toArray();
            } catch (QUI\Exception $Exception) {
                \QUI\System\Log::addDebug($Exception);
            }
        }

        // sort by priority
        \usort($result, function ($a, $b) {
            if (!isset($a['priority'])) {
                $a['priority'] = 0;
            }

            if (!isset($b['priority'])) {
                $b['priority'] = 0;
            }

            $priorityA = (int)$a['priority'];
            $priorityB = (int)$b['priority'];

            if ($priorityA === $priorityB) {
                return $a['id'] > $b['priority'];
            }

            return $priorityA > $priorityB;
        });

        return $result;
    },
    ['ruleIds'],
    'Permission::checkAdminUser'
);
