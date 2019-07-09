<?php

namespace QUI\ERP\Shipping\Api;

use QUI;

/**
 * Interface ShippingInterface
 *
 * @package QUI\ERP\Accounting\Shipping\Types
 */
interface ShippingTypeInterface
{
    //region general

    /**
     * @return string
     */
    public function getType();

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getTitle($Locale = null);

    //endregion

    /**
     * @param QUI\Locale|null $Locale
     * @return array
     */
    public function toArray($Locale = null);

    /**
     * @return string - Shipping method class
     */
    public function getShipping();
}
