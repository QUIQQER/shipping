<?php

/**
 * This file contains QUI\ERP\Shipping\Api\ShippingInterface
 */

namespace QUI\ERP\Shipping\Api;

use QUI\ERP\Shipping\Rules\ShippingRule;

/**
 * Interface for a Shipping Entry
 * All Shipping modules must implement this interface
 */
interface ShippingInterface
{
    /**
     * @return string
     */
    public function getId();

    /**
     * @return string
     */
    public function getTitle();

    /**
     * @return string
     */
    public function getDescription();

    /**
     * @return string
     */
    public function getIcon();

    /**
     * @return string
     */
    public function getShippingType();

    /**
     * @return ShippingRule|null
     */
    public function getShippingRule();

    /**
     * @return string
     */
    public function toArray();
}
