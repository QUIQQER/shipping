<?php

/**
 * This file contains QUI\ERP\Shipping\ShippingStatus\StatusUnknown
 */

namespace QUI\ERP\Shipping\ShippingStatus;

use QUI;

/**
 * Class Exception
 *
 * @package QUI\ERP\Shipping\ShippingStatus
 */
class StatusUnknown extends Status
{
    /**
     * @var int
     */
    protected int $id = 0;

    /**
     * @var string
     */
    protected mixed $color = '#999';

    /**
     * @var bool
     */
    protected bool $notification = false;

    /**
     * Status constructor
     */
    public function __construct()
    {
    }

    /**
     * Return the title
     *
     * @param null|QUI\Locale (optional) $Locale
     * @return string
     */
    public function getTitle($Locale = null)
    {
        if (!($Locale instanceof QUI\Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/shipping', 'shipping.status.unknown');
    }
}
