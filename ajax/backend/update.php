<?php

/**
 * This file contains package_quiqqer_shipping_ajax_backend_update
 */

use QUI\ERP\Shipping\Types\Factory;

/**
 * Update a shipping entry
 *
 * @param integer $shippingId - Shipping ID
 * @param array $data - Shipping Data
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_shipping_ajax_backend_update',
    function ($shippingId, $data) {
        $Factory       = new Factory();
        $ShippingEntry = $Factory->getChild($shippingId);

        $data = \json_decode($data, true);

        $ShippingEntry->setAttributes($data);

        /* @var $ShippingEntry \QUI\ERP\Shipping\Types\ShippingEntry */
        if (isset($data['title'])) {
            $ShippingEntry->setTitle($data['title']);
        }

        if (isset($data['workingTitle'])) {
            $ShippingEntry->setWorkingTitle($data['workingTitle']);
        }

        if (isset($data['description'])) {
            $ShippingEntry->setDescription($data['description']);
        }

        if (isset($data['icon'])) {
            $ShippingEntry->setIcon($data['icon']);
        } else {
            $ShippingEntry->removeIcon();
        }

        if (isset($data['shipping_rules'])) {
            $shipping = \json_decode($data['shipping_rules'], true);

            if (!\is_array($shipping)) {
                $shipping = [];
            }

            foreach ($shipping as $shippingId) {
                $ShippingEntry->addShippingRuleId($shippingId);
            }
        }

        $ShippingEntry->update();

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/shipping',
                'message.shipping.saved.successfully',
                [
                    'shipping' => $ShippingEntry->getTitle()
                ]
            )
        );

        return $ShippingEntry->toArray();
    },
    ['shippingId', 'data'],
    'Permission::checkAdminUser'
);
