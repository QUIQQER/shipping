<?php

namespace QUI\ERP\Shipping\Types;

use QUI;

/**
 * Interface ShippingInterface
 *
 * @package QUI\ERP\Accounting\Shipping\Types
 */
interface ShippingInterface
{
    //region general

    /**
     * @return integer
     */
    public function getId();

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
     * @return array
     */
    public function toArray();

    /**
     * @param string $hash - order hash
     * @return bool
     */
    public function isSuccessful($hash);

    /**
     * @return \QUI\ERP\Shipping\Api\AbstractShippingEntry
     */
    public function getShippingType();

    /**
     * @param QUI\Interfaces\Users\User $User
     * @return bool
     */
    public function canUsedBy(QUI\Interfaces\Users\User $User);
}
