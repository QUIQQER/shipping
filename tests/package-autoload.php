<?php

use Composer\Autoload\ClassLoader;

$PackageClassLoader = new ClassLoader();
$PackageClassLoader->addPsr4('QUI\\ERP\\Shipping\\', dirname(__DIR__) . '/src/QUI/ERP/Shipping');
$PackageClassLoader->addPsr4('QUITests\\ERP\\Shipping\\Unit\\', __DIR__ . '/unit');
$PackageClassLoader->addPsr4('QUITests\\ERP\\Shipping\\Integration\\', __DIR__ . '/integration');
$PackageClassLoader->addPsr4('QUITests\\ERP\\Shipping\\Stubs\\', __DIR__ . '/stubs');
$PackageClassLoader->register();
