<?php

/**
 * This file contains QUI\ERP\Shipping\Api\ShippingInterface
 */

namespace QUI\ERP\Shipping\Api;

/**
 * Interface for a Shipping Entry
 * All Shipping modules must implement this interface
 */
interface ShippingInterface
{
    /**
     * @return string
     */
    public function getTitle();

    /**
     * @return string
     */
    public function getDescription();
}
