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
     * @param QUI\Locale|null $Locale
     * @return array
     */
    public function toArray($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return [
            'title' => $this->getTitle($Locale),
            'type'  => $this->getType()
        ];
    }

    /**
     * @param $Locale
     * @return array|string
     */
    abstract public function getTitle($Locale = null);

    /**
     * @return string
     */
    abstract public function getIcon();
}
