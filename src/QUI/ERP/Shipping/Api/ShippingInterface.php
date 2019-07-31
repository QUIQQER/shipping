<?php

/**
 * This file contains QUI\ERP\Shipping\Api\ShippingInterface
 */

namespace QUI\ERP\Shipping\Api;

use QUI;

/**
 * Interface for a Shipping Entry
 * All Shipping modules must implement this interface
 */
interface ShippingInterface
{
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
     * @return string
     */
    public function getIcon();

    /**
     * @return string
     * @throws QUI\ERP\Shipping\Exception
     */
    public function getShippingType();

    /**
     * Return the price of the shipping entry
     *
     * @return float|int
     */
    public function getPrice();

    /**
     * Return the price display
     *
     * @return string
     */
    public function getPriceDisplay();

    //region attributes

    /**
     * @param string $name
     * @return mixed
     */
    public function getAttribute($name);

    /**
     * @return array
     */
    public function toArray();

    /**
     * Return the shipping as an json array
     *
     * @return string
     */
    public function toJSON();

    //endregion

    //region status

    /**
     * @return bool
     */
    public function isActive();

    /**
     * Activate ths shipping entry
     */
    public function activate();

    /**
     * Deactivate ths shipping entry
     */
    public function deactivate();

    //endregion
}
