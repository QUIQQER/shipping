<?php

if (!defined('QUIQQER_SYSTEM')) {
    define('QUIQQER_SYSTEM', true);
}

if (!defined('QUIQQER_AJAX')) {
    define('QUIQQER_AJAX', true);
}

putenv('QUIQQER_OTHER_AUTOLOADERS=KEEP');

if (file_exists(__DIR__ . '/../../../../bootstrap.php')) {
    require_once __DIR__ . '/../../../../bootstrap.php';
}

if (file_exists(__DIR__ . '/../../../autoload.php')) {
    require_once __DIR__ . '/../../../autoload.php';
}

require_once __DIR__ . '/package-autoload.php';

$optionalPhpUnitStubs = [
    QUI\ERP\Accounting\Invoice\InvoiceTemporary::class
        => __DIR__ . '/phpstan-stubs/QUI/ERP/Accounting/Invoice/InvoiceTemporary.php',
    QUI\ERP\Accounting\Invoice\InvoiceView::class
        => __DIR__ . '/phpstan-stubs/QUI/ERP/Accounting/Invoice/InvoiceView.php',
    QUI\ERP\Order\Guest\GuestOrderUser::class
        => __DIR__ . '/stubs/QUI/ERP/Order/Guest/GuestOrderUser.php'
];

foreach ($optionalPhpUnitStubs as $className => $stubFile) {
    if (!class_exists($className)) {
        require_once $stubFile;
    }
}
