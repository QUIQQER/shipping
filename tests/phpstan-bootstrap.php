<?php

if (!defined('QUIQQER_SYSTEM')) {
    define('QUIQQER_SYSTEM', true);
}

if (!defined('QUIQQER_AJAX')) {
    define('QUIQQER_AJAX', true);
}

putenv("QUIQQER_OTHER_AUTOLOADERS=KEEP");

require_once __DIR__ . '/../../../../bootstrap.php';

require_once __DIR__ . '/package-autoload.php';

$optionalClassStubs = [
    QUI\ERP\Accounting\Invoice\Invoice::class
        => 'QUI/ERP/Accounting/Invoice/Invoice.php',
    QUI\ERP\Accounting\Invoice\InvoiceTemporary::class
        => 'QUI/ERP/Accounting/Invoice/InvoiceTemporary.php',
    QUI\ERP\Accounting\Invoice\InvoiceView::class
        => 'QUI/ERP/Accounting/Invoice/InvoiceView.php',
    QUI\ERP\Accounting\Offers\AbstractOffer::class
        => 'QUI/ERP/Accounting/Offers/AbstractOffer.php',
    QUI\ERP\SalesOrders\SalesOrder::class
        => 'QUI/ERP/SalesOrders/SalesOrder.php'
];

foreach ($optionalClassStubs as $className => $stubFile) {
    if (!class_exists($className, false) && !interface_exists($className, false)) {
        require_once __DIR__ . '/phpstan-stubs/' . $stubFile;
    }
}
