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
    public function getType(): string;

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getTitle(QUI\Locale $Locale = null): string;

    /**
     * @return string
     */
    public function getIcon(): string;

    //endregion

    /**
     * @param QUI\Locale|null $Locale
     * @return array
     */
    public function toArray(QUI\Locale $Locale = null): array;
}
