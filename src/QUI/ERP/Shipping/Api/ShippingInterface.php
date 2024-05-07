<?php

/**
 * This file contains QUI\ERP\Shipping\Api\ShippingInterface
 */

namespace QUI\ERP\Shipping\Api;

use QUI;
use QUI\ERP\Shipping\Exception;

/**
 * Interface for a Shipping Entry
 * All Shipping modules must implement this interface
 */
interface ShippingInterface
{
    /**
     * @return int|string
     */
    public function getId(): int|string;

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getTitle(QUI\Locale $Locale = null): string;

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getDescription(QUI\Locale $Locale = null): string;

    /**
     * @return string
     */
    public function getIcon(): string;

    /**
     * @return ShippingTypeInterface
     * @throws Exception
     */
    public function getShippingType(): ShippingTypeInterface;

    /**
     * Return the price of the shipping entry
     *
     * @return float|int
     */
    public function getPrice(): float|int;

    /**
     * Return the price display
     *
     * @return string
     */
    public function getPriceDisplay(): string;

    //region attributes

    /**
     * @param string $name
     * @return mixed
     */
    public function getAttribute(string $name): mixed;

    /**
     * @return array
     */
    public function toArray(): array;

    /**
     * Return the shipping as a json array
     *
     * @return string
     */
    public function toJSON(): string;

    //endregion

    //region status

    /**
     * @return bool
     */
    public function isActive(): bool;

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
