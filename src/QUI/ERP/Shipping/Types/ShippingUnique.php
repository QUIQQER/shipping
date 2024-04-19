<?php

namespace QUI\ERP\Shipping\Types;

use QUI;
use QUI\ERP\Shipping\Api;
use QUI\ERP\Shipping\Api\ShippingInterface;

use QUI\ERP\Shipping\Api\ShippingTypeInterface;
use QUI\ERP\Shipping\Exception;

use function class_exists;
use function floatval;
use function json_encode;

/**
 * Class ShippingUnique
 * - This class represent a Shipping Entry, but it's only a database container
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
    protected array $attributes = [];

    /**
     * ShippingUnique constructor.
     *
     * @param array $attributes - shipping data
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * @return int|string
     */
    public function getId(): int|string
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
    public function getTitle($Locale = null): string
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
    public function getDescription($Locale = null): string
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
    public function getIcon(): string
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
    public function getPriceDisplay(): string
    {
        $Price = new QUI\ERP\Money\Price(
            $this->getPrice(),
            QUI\ERP\Defaults::getCurrency()
        );

        return '+' . $Price->getDisplayPrice();
    }

    /**
     * Return the price of the shipping entry
     *
     * @return float|int
     */
    public function getPrice(): float|int
    {
        if (isset($this->attributes['price'])) {
            return floatval($this->attributes['price']);
        }

        return 0;
    }

    /**
     * @return ShippingTypeInterface
     * @throws Exception
     */
    public function getShippingType(): Api\ShippingTypeInterface
    {
        $type = $this->getAttribute('shipping_type');

        if (!class_exists($type)) {
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
    public function getAttribute(string $name): mixed
    {
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }

        return '';
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Return the shipping as a json array
     *
     * @return string
     */
    public function toJSON(): string
    {
        return json_encode($this->toArray());
    }

    //endregion

    //region status

    /**
     * @return bool
     */
    public function isActive(): bool
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
