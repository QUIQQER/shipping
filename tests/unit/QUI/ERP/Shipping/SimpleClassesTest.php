<?php

namespace QUI\ERP\Shipping;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Shipping\Methods\Digital\ShippingType as DigitalShippingType;
use QUI\ERP\Shipping\Methods\Standard\ShippingType as StandardShippingType;
use QUI\ERP\Shipping\ShippingStatus\StatusUnknown;
use QUI\ERP\Shipping\Tracking\Tracking;
use QUI\ERP\Shipping\Types\ShippingUnique;

class SimpleClassesTest extends TestCase
{
    public function testProviderExposesBothBuiltInShippingTypes(): void
    {
        $Provider = new Provider();

        self::assertSame(
            [StandardShippingType::class, DigitalShippingType::class],
            $Provider->getShippingTypes()
        );

        foreach ($Provider->getShippingTypes() as $shippingTypeClass) {
            $ShippingType = new $shippingTypeClass();
            self::assertSame($shippingTypeClass, $ShippingType->getType());
            self::assertSame($shippingTypeClass, $ShippingType->toArray()['type']);
            self::assertNotSame('', $ShippingType->getTitle());
            self::assertNotSame('', $ShippingType->getIcon());
        }
    }

    public function testShippingUniqueProvidesImmutableSnapshotValues(): void
    {
        $language = QUI::getLocale()->getCurrent();
        $Shipping = new ShippingUnique([
            'id' => 42,
            'title' => [$language => 'PHPUnit shipping'],
            'description' => [$language => 'PHPUnit description'],
            'icon' => '/icon.svg',
            'price' => '12.5',
            'shipping_type' => StandardShippingType::class
        ]);

        self::assertSame(42, $Shipping->getId());
        self::assertSame('PHPUnit shipping', $Shipping->getTitle());
        self::assertSame('PHPUnit description', $Shipping->getDescription());
        self::assertSame('/icon.svg', $Shipping->getIcon());
        self::assertSame(12.5, $Shipping->getPrice());
        self::assertStringContainsString('12', $Shipping->getPriceDisplay());
        self::assertInstanceOf(StandardShippingType::class, $Shipping->getShippingType());
        self::assertSame(42, $Shipping->toArray()['id']);
        self::assertJson($Shipping->toJSON());
        self::assertTrue($Shipping->isActive());
        self::assertNull($Shipping->activate());
        self::assertNull($Shipping->deactivate());
        self::assertSame('', $Shipping->getAttribute('missing'));
    }

    public function testShippingUniqueDefaultsAndInvalidType(): void
    {
        $Shipping = new ShippingUnique();

        self::assertSame(0, $Shipping->getId());
        self::assertSame('', $Shipping->getTitle());
        self::assertSame('', $Shipping->getDescription());
        self::assertSame('', $Shipping->getIcon());
        self::assertSame(0, $Shipping->getPrice());

        $this->expectException(QUI\ERP\Shipping\Exception::class);
        $Shipping->getShippingType();
    }

    public function testDebugStackAndLoggers(): void
    {
        Debug::clearLogStock();
        Debug::disable();
        Debug::addLog('hidden');
        self::assertSame([], Debug::getLogStack());

        Debug::enable();
        Debug::addLog('visible');
        self::assertTrue(Debug::isEnabled());
        self::assertSame(['visible'], Debug::getLogStack());

        Debug::ruleIsDebugged('phpunit-rule');
        self::assertTrue(Debug::isRuleAlreadyDebugged('phpunit-rule'));
        self::assertNotNull(Debug::getLogger());
        self::assertNotNull(Debug::getLoggerWithoutFormatter());

        Debug::disable();
        Debug::clearLogStock();
    }

    public function testUnknownStatusHasStableDefaults(): void
    {
        $Status = new StatusUnknown();

        self::assertSame(0, $Status->getId());
        self::assertSame('#999', $Status->getColor());
        self::assertFalse($Status->isAutoNotification());
        self::assertNotSame('', $Status->getTitle());
        self::assertSame(0, $Status->toArray()['id']);
    }

    public function testTrackingInstallationFilteringAndCarrierUrls(): void
    {
        $file = Tracking::getConfigFile();
        $existed = file_exists($file);
        $previous = $existed ? file_get_contents($file) : null;

        try {
            if ($existed) {
                unlink($file);
            }

            Tracking::onPackageInstall();
            self::assertFileExists($file);
            $carriers = Tracking::getActiveCarriers();
            self::assertArrayHasKey(0, $carriers);
            self::assertStringEndsWith('123456', Tracking::getUrl('123456', 'ups', null));

            $Country = $this->createMock(QUI\Countries\Country::class);
            $Country->method('getCode')->willReturn('DE');
            self::assertStringContainsString(
                'tracking-id=123456',
                Tracking::getUrl('123456', 'dhl', $Country)
            );
            self::assertSame('', Tracking::getUrl('123456', 'unknown-carrier', $Country));
        } finally {
            if ($previous !== null) {
                file_put_contents($file, $previous);
            } elseif (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
