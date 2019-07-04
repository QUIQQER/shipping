<?php

/**
 * This file contains QUI\ERP\Shipping\Provider
 */

namespace QUI\ERP\Shipping;

use QUI;
use QUI\ERP\Shipping\Api\AbstractShippingProvider;

/**
 * Class Provider
 *
 * @package QUI\ERP\Shipping
 */
class Provider extends AbstractShippingProvider
{
    /**
     * @return array
     */
    public function getShippingTypes()
    {
        return [
            Methods\Standard\ShippingType::class
        ];
    }
}
