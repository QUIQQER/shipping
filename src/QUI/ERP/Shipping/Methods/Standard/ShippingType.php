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
     * @param null $Locale
     * @return array|string
     */
    public function getTitle($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/shipping', 'shipping.standard.title');
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return URL_OPT_DIR.'quiqqer/shipping/bin/images/default.png';
    }

    /**
     * @return string
     */
    public function getShipping()
    {
        return Shipping::class;
    }
}
