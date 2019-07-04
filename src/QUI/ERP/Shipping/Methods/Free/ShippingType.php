<?php

/**
 * This file contains QUI\ERP\Shipping\Methods\Free\ShippingType
 */

namespace QUI\ERP\Shipping\Methods\Free;

use QUI;

/**
 * Class ShippingType
 * - This class is a placeholder / helper class for the free shipping
 * - if an order has no value of goods, this shipping will be used
 *
 * @package QUI\ERP\Shipping\Methods\Free\ShippingType
 */
class ShippingType extends QUI\QDOM implements QUI\ERP\Shipping\Api\ShippingInterface
{
    /**
     * @return array
     */
    public function toArray()
    {
        $lg     = 'quiqqer/shipping';
        $Locale = QUI::getLocale();

        return [
            'title'        => $Locale->get($lg, 'shipping.free.title'),
            'description'  => $Locale->get($lg, 'shipping.free.description'),
            'workingTitle' => $Locale->get($lg, 'shipping.free.workingTitle'),
            'shippingType' => false,
            'icon'         => ''
        ];
    }

    /**
     * @param string $hash
     * @return bool
     */
    public function isSuccessful($hash)
    {
        return true;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return -1;
    }

    /**
     * @param $Locale
     * @return array|string
     */
    public function getTitle($Locale = null)
    {
        $ShippingType = $this->getShippingType();

        if ($Locale !== null) {
            $ShippingType->setLocale($Locale);
        }

        return $ShippingType->getTitle();
    }

    /**
     * @param $Locale
     * @return array|string
     */
    public function getWorkingTitle($Locale = null)
    {
        $ShippingType = $this->getShippingType();

        if ($Locale !== null) {
            $ShippingType->setLocale($Locale);
        }

        return $ShippingType->getWorkingTitle();
    }

    /**
     * @param $Locale
     * @return array|string
     */
    public function getDescription($Locale = null)
    {
        $ShippingType = $this->getShippingType();

        if ($Locale !== null) {
            $ShippingType->setLocale($Locale);
        }

        return $ShippingType->getDescription();
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return $this->getShippingType()->getIcon();
    }

    /**
     * @return Shipping
     */
    public function getShippingType()
    {
        return new Shipping();
    }

    /**
     * @param QUI\Interfaces\Users\User $User
     * @return bool
     */
    public function canUsedBy(QUI\Interfaces\Users\User $User)
    {
        return true;
    }
}
