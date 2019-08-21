<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_rules_settings_getUnitFields
 */

use QUI\ERP\Products\Handler\Fields;

/**
 * Return all available unit fields
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_rules_settings_getUnitFields',
    function () {
        $unitSelects = Fields::getFieldsByType(Fields::TYPE_UNITSELECT);
        $unitSelects = \array_map(function ($Field) {
            /* @var $Field QUI\ERP\Products\Field\Field */
            return $Field->getAttributes();
        }, $unitSelects);

        return $unitSelects;
    },
    false,
    'Permission::checkAdminUser'
);
