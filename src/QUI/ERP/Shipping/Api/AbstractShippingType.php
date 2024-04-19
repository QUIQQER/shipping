<?php

/**
 * This file contains \QUI\ERP\Shipping\Api\AbstractShippingEntry
 */

namespace QUI\ERP\Shipping\Api;

use QUI;

use function get_class;

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
    public function getType(): string
    {
        return get_class($this);
    }

    /**
     * @param QUI\Locale|null $Locale
     * @return array
     */
    public function toArray(QUI\Locale $Locale = null): array
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return [
            'title' => $this->getTitle($Locale),
            'type' => $this->getType()
        ];
    }

    /**
     * @param QUI\Locale|null $Locale
     * @return string
     */
    abstract public function getTitle(QUI\Locale $Locale = null): string;

    /**
     * @return string
     */
    abstract public function getIcon(): string;
}
