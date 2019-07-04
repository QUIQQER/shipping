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

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getDescription($Locale = null);

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getWorkingTitle($Locale = null);

    //endregion

    /**
     * @param QUI\Locale|null $Locale
     * @return array
     */
    public function toArray($Locale = null);

    /**
     * @return \QUI\ERP\Shipping\Api\AbstractShippingEntry
     */
    public function getShipping();

    /**
     * @param QUI\Interfaces\Users\User $User
     * @return bool
     */
    public function canUsedBy(QUI\Interfaces\Users\User $User);
}
