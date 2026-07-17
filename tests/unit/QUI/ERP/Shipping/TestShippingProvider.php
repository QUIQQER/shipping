<?php

namespace QUI\ERP\Shipping;

use QUI\ERP\Shipping\Api\AbstractShippingProvider;
use QUI\ERP\Shipping\Methods\Digital\ShippingType as DigitalShippingType;
use QUI\ERP\Shipping\Methods\Standard\ShippingType as StandardShippingType;

class TestShippingProvider extends AbstractShippingProvider
{
    public function getShippingTypes(): array
    {
        return [
            'Missing\\Shipping\\Type',
            \stdClass::class,
            StandardShippingType::class,
            DigitalShippingType::class
        ];
    }
}
