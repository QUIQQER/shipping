<?php

namespace QUI\ERP\Shipping;

class TestShippingService extends Shipping
{
    public array $shippingList = [];

    public function __construct()
    {
    }

    public function getShippingProviders(): array
    {
        return [new TestShippingProvider()];
    }

    public function getShippingList(array $queryParams = []): array
    {
        return $this->shippingList;
    }
}
