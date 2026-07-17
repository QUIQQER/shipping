<?php

namespace QUITests\ERP\Shipping\Stubs;

use QUI\ERP\ErpEntityInterface;
use QUI\ERP\Shipping\Api\AbstractShippingType;
use QUI\ERP\Shipping\Api\ShippingInterface;
use QUI\Interfaces\Users\User;

class AlwaysAvailableShippingType extends AbstractShippingType
{
    public function getTitle(?\QUI\Locale $Locale = null): string
    {
        return 'PHPUnit shipping';
    }

    public function getIcon(): string
    {
        return '/phpunit-shipping.svg';
    }

    public function canUsedIn(ErpEntityInterface $Entity, ShippingInterface $Shipping): bool
    {
        return true;
    }

    public function canUsedBy(User $User, ShippingInterface $Shipping, ErpEntityInterface $Entity): bool
    {
        return true;
    }
}
