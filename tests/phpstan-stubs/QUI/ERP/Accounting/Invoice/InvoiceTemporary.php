<?php

namespace QUI\ERP\Accounting\Invoice;

if (!class_exists(InvoiceTemporary::class, false)) {
    abstract class InvoiceTemporary implements \QUI\ERP\ErpEntityInterface
    {
        abstract public function addCustomDataEntry(string $key, mixed $value): void;

        abstract public function getCustomDataEntry(string $key): mixed;

        abstract public function update(?\QUI\Interfaces\Users\User $PermissionUser = null): void;
    }
}
