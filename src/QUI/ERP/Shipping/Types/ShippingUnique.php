<?php

namespace QUI\ERP\Shipping\Types;

use QUI\ERP\Shipping\Api\ShippingInterface;

/**
 * Class ShippingUnique
 * - This class represent a Shipping Entry, but its only a database container
 * - This shipping is created from pure shipping data, for example an invoice.
 * - This shipping cannot perform database operations.
 *
 * @package QUI\ERP\Shipping\Types
 */
class ShippingUnique implements ShippingInterface
{
    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * ShippingUnique constructor.
     *
     * @param array $attributes - shipping data
     */
    public function __construct($attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * @return string
     */
    public function getId()
    {

    }

    /**
     * @return string
     */
    public function getTitle()
    {
        if (isset($this->attributes['title'])) {

        }

        return '';
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        if (isset($this->attributes['description'])) {

        }

        return '';
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        if (isset($this->attributes['icon'])) {
            return $this->attributes['icon'];
        }

        return '';
    }

    /**
     * @return string
     */
    public function getShippingType()
    {

    }

    //region attributes

    /**
     * @param string $name
     * @return mixed
     */
    public function getAttribute($name)
    {
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }

        return '';
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->attributes;
    }

    //endregion

    //region status

    /**
     * @return bool
     */
    public function isActive()
    {
        return true;
    }

    /**
     * Activate ths shipping entry
     */
    public function activate()
    {
        // nothing
    }

    /**
     * Deactivate ths shipping entry
     */
    public function deactivate()
    {
        // nothing
    }

    //endregion
}