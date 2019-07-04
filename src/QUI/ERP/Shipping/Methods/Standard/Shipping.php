<?php

/**
 * This file contains QUI\ERP\Shipping\Methods\Standard\Shipping
 */

namespace QUI\ERP\Shipping\Methods\Standard;

use QUI;
use QUI\ERP\Shipping\Shipping as ShippingHandler;

/**
 * Class Shipping
 *
 * @package QUI\ERP\Shipping\Methods\Standard\Shipping
 */
class Shipping extends QUI\ERP\Shipping\Api\AbstractShippingEntry
{
    /**
     * Standard shipping id
     */
    const ID = -1;

    /**
     * @return array|string
     */
    public function getTitle()
    {
        return $this->getLocale()->get(
            'quiqqer/shipping',
            'shipping.standard.title'
        );
    }

    /**
     * @return array|string
     */
    public function getWorkingTitle()
    {
        return $this->getLocale()->get(
            'quiqqer/shipping',
            'shipping.standard.workingTitle'
        );
    }

    /**
     * @return array|string
     */
    public function getDescription()
    {
        return $this->getLocale()->get(
            'quiqqer/shipping',
            'shipping.standard.description'
        );
    }

    /**
     * Return the shipping icon (the URL path)
     * Can be overwritten
     *
     * @return string
     */
    public function getIcon()
    {
        return ShippingHandler::getInstance()->getHost().
               URL_OPT_DIR.
               'quiqqer/shipping/bin/shipping/standard.png';
    }
}
