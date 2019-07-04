<?php

/**
 * This file contains QUI\ERP\Shipping\Methods\Free\Shipping
 */

namespace QUI\ERP\Shipping\Methods\Free;

use QUI;
use QUI\ERP\Shipping\Shipping as ShippingHandler;

/**
 * Class Shipping
 *
 * @package QUI\ERP\Shipping\Methods\Free\Shipping
 */
class Shipping extends QUI\ERP\Shipping\Api\AbstractShippingEntry
{
    /**
     * free shipping id
     */
    const ID = -1;

    /**
     * @return array|string
     */
    public function getTitle()
    {
        return $this->getLocale()->get(
            'quiqqer/shipping',
            'shipping.free.title'
        );
    }

    /**
     * @return array|string
     */
    public function getWorkingTitle()
    {
        return $this->getLocale()->get(
            'quiqqer/shipping',
            'shipping.free.workingTitle'
        );
    }

    /**
     * @return array|string
     */
    public function getDescription()
    {
        return $this->getLocale()->get(
            'quiqqer/shipping',
            'shipping.free.description'
        );
    }

    /**
     * @return bool
     */
    public function isGateway()
    {
        return false;
    }

    /**
     * @param string $hash
     * @return bool
     */
    public function isSuccessful($hash)
    {
        try {
            $Order       = QUI\ERP\Order\Handler::getInstance()->getOrderByHash($hash);
            $Calculation = $Order->getPriceCalculation();

            if ($Calculation->getSum() === 0) {
                return true;
            }
        } catch (\Exception $Exception) {
        }

        return false;
    }

    /**
     * @return bool
     */
    public function refundSupport()
    {
        return false;
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
               'quiqqer/shipping/bin/shipping/Free.png';
    }
}
