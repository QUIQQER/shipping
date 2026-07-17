<?php

namespace QUI\ERP\Accounting\Invoice;

if (!class_exists(Invoice::class, false)) {
    abstract class Invoice implements \QUI\ERP\ErpEntityInterface
    {
    }
}
