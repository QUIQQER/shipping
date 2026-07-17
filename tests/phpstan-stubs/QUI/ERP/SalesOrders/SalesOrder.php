<?php

namespace QUI\ERP\SalesOrders;

if (!class_exists(SalesOrder::class, false)) {
    abstract class SalesOrder implements \QUI\ERP\ErpEntityInterface
    {
        abstract public function update(): void;
    }
}
