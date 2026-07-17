<?php

use Composer\Autoload\ClassLoader;

$PackageClassLoader = new ClassLoader();
$PackageClassLoader->addPsr4('QUI\\ERP\\Shipping\\', dirname(__DIR__) . '/src/QUI/ERP/Shipping');
$PackageClassLoader->addPsr4(
    'QUI\\ERP\\Shipping\\Tests\\Stubs\\',
    __DIR__ . '/stubs/QUI/ERP/Shipping'
);
$PackageClassLoader->register();
