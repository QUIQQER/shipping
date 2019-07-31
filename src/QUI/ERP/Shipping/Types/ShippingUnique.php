<?php

namespace QUI\ERP\Shipping\Types;

use QUI;
use QUI\ERP\Shipping\Api;
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
     * @return int
     */
    public function getId()
    {
        if (isset($this->attributes['id'])) {
            return $this->attributes['id'];
        }

        return 0;
    }

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getTitle($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        $current = $Locale->getCurrent();

        if (isset($this->attributes['title']) && $this->attributes['title'][$current]) {
            return $this->attributes['title'][$current];
        }

        return '';
    }

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getDescription($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        $current = $Locale->getCurrent();

        if (isset($this->attributes['description']) && $this->attributes['description'][$current]) {
            return $this->attributes['description'][$current];
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
     * Return the price display
     *
     * @return string
     */
    public function getPriceDisplay()
    {
        $Price = new QUI\ERP\Money\Price(
            $this->getPrice(),
            QUI\ERP\Defaults::getCurrency()
        );

        return '+'.$Price->getDisplayPrice();
    }

    /**
     * Return the price of the shipping entry
     *
     * @return float|int
     */
    public function getPrice()
    {
        if (isset($this->attributes['price'])) {
            return \floatval($this->attributes['price']);
        }

        return 0;
    }

    /**
     * @return string
     * @throws QUI\ERP\Shipping\Exception
     */
    public function getShippingType()
    {
        $type = $this->getAttribute('shipping_type');

        if (!\class_exists($type)) {
            throw new QUI\ERP\Shipping\Exception([
                'quiqqer/shipping',
                'exception.shipping.type.not.found',
                ['shippingType' => $type]
            ]);
        }

        $Type = new $type();

        if (!($Type instanceof Api\ShippingTypeInterface)) {
            throw new QUI\ERP\Shipping\Exception([
                'quiqqer/shipping',
                'exception.shipping.type.not.abstractShipping',
                ['shippingType' => $type]
            ]);
        }

        return $Type;
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

    /**
     * Return the shipping as an json array
     *
     * @return string
     */
    public function toJSON()
    {
        return \json_encode($this->toArray());
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
