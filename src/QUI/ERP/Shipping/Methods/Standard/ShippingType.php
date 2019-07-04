<?php

/**
 * This file contains QUI\ERP\Shipping\Methods\Standard\ShippingType
 */

namespace QUI\ERP\Shipping\Methods\Standard;

use QUI;

/**
 * Class ShippingType
 * - This class is a placeholder / helper class for the standard shipping
 *
 * @package QUI\ERP\Shipping\Methods\Free\ShippingType
 */
class ShippingType extends QUI\ERP\Shipping\Api\AbstractShippingType
{
    /**
     * @return Shipping
     */
    public function getShippingType()
    {
        return new Shipping();
    }
}
