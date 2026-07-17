<?php

namespace QUI\ERP\Accounting\Offers;

if (!class_exists(AbstractOffer::class, false)) {
    abstract class AbstractOffer implements \QUI\ERP\ErpEntityInterface
    {
        abstract public function addCustomDataEntry(string $key, mixed $value): void;

        abstract public function getCustomDataEntry(string $key): mixed;
    }
}
