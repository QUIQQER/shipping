<?php

namespace QUI\ERP\Shipping\Tests\Stubs;

use QUI;
use QUI\ERP\Areas\Area;
use QUI\ERP\Areas\Handler as AreasHandler;
use QUI\ERP\Tax\Handler as TaxHandler;
use QUI\ERP\Tax\Utils as TaxUtils;
use QUI\Permissions\Permission;
use ReflectionProperty;
use RuntimeException;
use Throwable;

final class DefaultTaxEnvironment
{
    private const AREA_TITLE = 'PHPUnit Shipping default area';

    private static bool $cleanupRegistered = false;
    private static bool $ready = false;
    private static bool $defaultAreaChanged = false;
    private static mixed $originalDefaultArea = null;
    private static array|false|null $originalTaxGroups = null;
    private static array|false|null $originalTaxTypes = null;
    private static ?int $createdAreaId = null;
    private static ?int $createdTaxEntryId = null;
    private static ?int $createdTaxTypeId = null;

    public static function ensure(): void
    {
        if (self::$ready) {
            return;
        }

        self::registerCleanup();

        try {
            $Area = QUI\ERP\Defaults::getArea();
            $TaxType = TaxUtils::getTaxTypeByArea($Area);
            $TaxEntry = TaxUtils::getTaxEntry($TaxType, $Area);

            if ($TaxEntry->isActive()) {
                self::$ready = true;
                return;
            }
        } catch (Throwable) {
        }

        try {
            self::runAsSystemUser(static function (): void {
                $Area = self::getOrCreateDefaultArea();
                self::createDefaultTax($Area);
            });
            self::$ready = true;
        } catch (Throwable $Exception) {
            self::cleanup();

            throw new RuntimeException(
                'The PHPUnit Shipping tax environment could not be created: ' . $Exception->getMessage(),
                0,
                $Exception
            );
        }
    }

    public static function cleanup(): void
    {
        if (
            !self::$ready
            && self::$createdAreaId === null
            && self::$createdTaxEntryId === null
            && self::$createdTaxTypeId === null
        ) {
            return;
        }

        try {
            self::runAsSystemUser(static function (): void {
                self::deleteCreatedTax();
                self::restoreTaxConfig();
                self::restoreDefaultArea();
                self::deleteCreatedArea();
            });
        } catch (Throwable) {
            self::deleteFixturesDirectly();
            self::runCleanupStep(static function (): void {
                self::restoreTaxConfig();
            });
            self::runCleanupStep(static function (): void {
                self::restoreDefaultArea();
            });
        } finally {
            self::clearTaxRuntimeCache();
            self::$ready = false;
            self::$defaultAreaChanged = false;
            self::$originalDefaultArea = null;
            self::$originalTaxGroups = null;
            self::$originalTaxTypes = null;
            self::$createdAreaId = null;
            self::$createdTaxEntryId = null;
            self::$createdTaxTypeId = null;
        }
    }

    private static function getOrCreateDefaultArea(): Area
    {
        try {
            return QUI\ERP\Defaults::getArea();
        } catch (QUI\Exception) {
        }

        $Config = QUI::getPackage('quiqqer/tax')->getConfig();

        if ($Config === null) {
            throw new RuntimeException('The tax configuration is not available.');
        }

        self::$originalDefaultArea = $Config->getValue('shop', 'area');
        self::$defaultAreaChanged = true;
        $Country = QUI\ERP\Defaults::getCountry();
        $Area = (new AreasHandler())->createChild([
            'countries' => $Country->getCode(),
            'data' => json_encode([
                'importLocale' => self::AREA_TITLE
            ], JSON_THROW_ON_ERROR)
        ]);

        if (!$Area instanceof Area) {
            throw new RuntimeException('The default area could not be created.');
        }

        self::$createdAreaId = (int)$Area->getId();
        $Config->set('shop', 'area', (string)self::$createdAreaId);
        $Config->save();

        return $Area;
    }

    private static function createDefaultTax(Area $Area): void
    {
        $Taxes = new TaxHandler();
        $Config = $Taxes->getConfig();
        self::$originalTaxGroups = $Config->getSection('taxgroups');
        self::$originalTaxTypes = $Config->getSection('taxtypes');
        $TaxType = $Taxes->createTaxType();
        self::$createdTaxTypeId = $TaxType->getId();
        $groups = self::$originalTaxGroups;

        if (!is_array($groups)) {
            $groups = [];
        }

        $groupTypes = array_filter(explode(',', (string)($groups[0] ?? '')), 'strlen');
        $groupTypes[] = (string)$TaxType->getId();
        $groups[0] = implode(',', array_unique($groupTypes));
        $Config->setSection('taxgroups', $groups);
        $Config->save();
        $TaxEntry = $Taxes->createChild([
            'areaId' => $Area->getId(),
            'taxTypeId' => $TaxType->getId(),
            'taxGroupId' => 0,
            'vat' => 0,
            'active' => 1,
            'euvat' => 0
        ]);
        self::$createdTaxEntryId = (int)$TaxEntry->getId();
    }

    private static function deleteCreatedTax(): void
    {
        $Taxes = new TaxHandler();

        if (self::$createdTaxEntryId !== null) {
            try {
                $Taxes->getChild(self::$createdTaxEntryId)->delete();
            } catch (Throwable) {
                QUI::getDataBaseConnection()->delete(
                    QUI\Utils\Doctrine::quoteIdentifier($Taxes->getDataBaseTableName()),
                    ['id' => self::$createdTaxEntryId]
                );
            }
        }

        if (self::$createdTaxTypeId !== null) {
            $Taxes->deleteTaxType(self::$createdTaxTypeId);
        }
    }

    private static function restoreTaxConfig(): void
    {
        if (self::$originalTaxGroups === null && self::$originalTaxTypes === null) {
            return;
        }

        $Config = (new TaxHandler())->getConfig();

        if (self::$originalTaxGroups === false) {
            $Config->del('taxgroups');
        } elseif (is_array(self::$originalTaxGroups)) {
            $Config->setSection('taxgroups', self::$originalTaxGroups);
        }

        if (self::$originalTaxTypes === false) {
            $Config->del('taxtypes');
        } elseif (is_array(self::$originalTaxTypes)) {
            $Config->setSection('taxtypes', self::$originalTaxTypes);
        }

        $Config->save();
    }

    private static function restoreDefaultArea(): void
    {
        if (!self::$defaultAreaChanged) {
            return;
        }

        $Config = QUI::getPackage('quiqqer/tax')->getConfig();

        if ($Config === null) {
            return;
        }

        if (self::$originalDefaultArea === false || self::$originalDefaultArea === null) {
            $Config->del('shop', 'area');
        } else {
            $Config->set('shop', 'area', self::$originalDefaultArea);
        }

        $Config->save();
    }

    private static function deleteCreatedArea(): void
    {
        if (self::$createdAreaId === null) {
            return;
        }

        try {
            (new AreasHandler())->getChild(self::$createdAreaId)->delete();
        } catch (Throwable) {
            QUI::getDataBaseConnection()->delete(
                QUI::getDBTableName('areas'),
                ['id' => self::$createdAreaId]
            );
        }
    }

    private static function deleteFixturesDirectly(): void
    {
        try {
            if (self::$createdTaxEntryId !== null) {
                QUI::getDataBaseConnection()->delete(
                    QUI::getDBTableName('tax'),
                    ['id' => self::$createdTaxEntryId]
                );
            }

            if (self::$createdAreaId !== null) {
                QUI::getDataBaseConnection()->delete(
                    QUI::getDBTableName('areas'),
                    ['id' => self::$createdAreaId]
                );
            }
        } catch (Throwable) {
        }
    }

    private static function clearTaxRuntimeCache(): void
    {
        try {
            (new ReflectionProperty(TaxUtils::class, 'userTaxes'))->setValue(null, []);
        } catch (Throwable) {
        }
    }

    private static function runCleanupStep(callable $Callback): void
    {
        try {
            $Callback();
        } catch (Throwable) {
        }
    }

    private static function runAsSystemUser(callable $Callback): mixed
    {
        $PermissionUser = new ReflectionProperty(Permission::class, 'User');
        $previousPermissionUser = $PermissionUser->getValue();
        Permission::setUser(QUI::getUsers()->getSystemUser());

        try {
            return $Callback();
        } finally {
            $PermissionUser->setValue(null, $previousPermissionUser);
        }
    }

    private static function registerCleanup(): void
    {
        if (self::$cleanupRegistered) {
            return;
        }

        self::$cleanupRegistered = true;

        if (class_exists(QUI\System\TestCleanup::class)) {
            QUI\System\TestCleanup::register();
            QUI::getEvents()->addEvent(
                QUI\System\TestCleanup::EVENT,
                static function (): void {
                    self::cleanup();
                }
            );

            return;
        }

        register_shutdown_function(static function (): void {
            self::cleanup();
        });
    }
}
