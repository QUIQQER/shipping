<?php

namespace QUI\ERP\Shipping\Order;

class TestableShippingStep extends Shipping
{
    public array $validShipping = [];

    protected function getValidShipping(): array
    {
        return $this->validShipping;
    }
}
