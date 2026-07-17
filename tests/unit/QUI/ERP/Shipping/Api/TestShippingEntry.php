<?php

namespace QUI\ERP\Shipping\Api;

use QUI;
use QUI\ERP\Shipping\Methods\Standard\ShippingType;

class TestShippingEntry extends AbstractShippingEntry
{
    public function __construct()
    {
    }

    public function getId(): int|string
    {
        return 1;
    }

    public function getTitle($Locale = null): string
    {
        return 'Test shipping';
    }

    public function getDescription(null|QUI\Locale $Locale = null): string
    {
        return 'Test description';
    }

    public function getWorkingTitle(): string
    {
        return 'Test working title';
    }

    public function getShippingType(): ShippingTypeInterface
    {
        return new ShippingType();
    }

    public function getPrice(): float|int
    {
        return 0;
    }

    public function getPriceDisplay(): string
    {
        return '';
    }

    public function getAttribute(string $name): mixed
    {
        return null;
    }

    public function toJSON(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public function isActive(): bool
    {
        return true;
    }

    public function activate(): void
    {
    }

    public function deactivate(): void
    {
    }
}
