<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_getShippingList
 */

use QUI\Utils\Grid;

/**
 * Return all active shipping entries
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_rules_getList',
    function ($options) {
        $options = json_decode($options, true);

        if (!is_array($options)) {
            $options = [];
        }

        if (!isset($options['page'])) {
            $options['page'] = 1;
        }

        if (!isset($options['sortOn'])) {
            $options['sortOn'] = 'priority';
        }

        switch ($options['sortOn']) {
            case 'id':
            case 'priority':
            case 'discount':
            case 'discount_type':
                break;

            case 'statusNode':
                $options['sortOn'] = 'active';
                break;

            default:
                $options['sortOn'] = 'priority';
        }

        $Factory = QUI\ERP\Shipping\Rules\Factory::getInstance();
        $Grid = new Grid();
        $query = $Grid->parseDBParams($options);

        if (!isset($query['order'])) {
            $query['order'] = 'priority DESC';
        }

        $rules = $Factory->getChildren($query);


        $result = [];

        foreach ($rules as $Rule) {
            /* @var $Rule \QUI\ERP\Shipping\Rules\ShippingRule */
            $result[] = $Rule->toArray();
        }

        // count
        unset($query['limit']);
        unset($query['order']);

        $count = $Factory->countChildren($query);

        return [
            'data' => $result,
            'page' => (int)$options['page'],
            'total' => $count
        ];
    },
    ['options'],
    'Permission::checkAdminUser'
);
