<?php

/**
 * This file contains \QUI\ERP\Shipping\Api\AbstractShippingEntry
 */

namespace QUI\ERP\Shipping\Api;

use QUI;

/**
 * Shipping abstract class
 * This is the parent shipping class for all shipping methods
 *
 * @author www.pcsg.de (Henning Leutz)
 */
abstract class AbstractShippingType extends QUI\QDOM implements QUI\ERP\Shipping\Api\ShippingTypeInterface
{
    /**
     * @return string
     */
    public function getType()
    {
        return \get_class($this);
    }

    /**
     * @param QUI\Interfaces\Users\User $User
     * @return bool
     */
    public function canUsedBy(QUI\Interfaces\Users\User $User)
    {
        return true;
    }

    /**
     * @param QUI\Locale|null $Locale
     * @return array
     */
    public function toArray($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return [
            'title'        => $this->getTitle($Locale),
            'description'  => $this->getDescription($Locale),
            'workingTitle' => $this->getWorkingTitle($Locale),
            'shippingType' => $this->getType(),
            'icon'         => $this->getIcon()
        ];
    }

    /**
     * @param $Locale
     * @return array|string
     */
    public function getName($Locale = null)
    {
        $ShippingType = $this->getShipping();

        if ($Locale !== null) {
            $ShippingType->setLocale($Locale);
        }

        return $ShippingType->getTitle();
    }

    /**
     * @param $Locale
     * @return array|string
     */
    public function getTitle($Locale = null)
    {
        $ShippingType = $this->getShipping();

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
        $ShippingType = $this->getShipping();

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
        $ShippingType = $this->getShipping();

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
        return $this->getShipping()->getIcon();
    }
}
