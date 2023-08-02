<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_rules_settings_getUnitFieldSetting
 */

use QUI\ERP\Products\Handler\Fields;

/**
 * Return the unit fields which are set up for the unit definition
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_rules_settings_getUnitFieldSetting',
    function () {
        $result = [];
        $Shipping = \QUI\ERP\Shipping\Shipping::getInstance();
        $ids = $Shipping->getShippingRuleUnitFieldIds();

        foreach ($ids as $id) {
            try {
                $Field = Fields::getField($id);

                if ($Field->getType() === Fields::TYPE_UNITSELECT) {
                    $result[] = $Field->getAttributes();
                }
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);;
            }
        }

        return $result;
    },
    false,
    'Permission::checkAdminUser'
);
