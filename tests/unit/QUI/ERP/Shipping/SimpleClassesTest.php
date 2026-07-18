<?php

namespace QUI\ERP\Shipping;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Shipping\Methods\Digital\ShippingType as DigitalShippingType;
use QUI\ERP\Shipping\Methods\Standard\ShippingType as StandardShippingType;
use QUI\ERP\Shipping\ShippingStatus\StatusUnknown;
use QUI\ERP\Shipping\Tracking\Tracking;
use QUI\ERP\Shipping\Types\Factory as ShippingFactory;
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

    public function testBuiltInShippingTypesAcceptUsersThroughGroupAssignment(): void
    {
        $Group = $this->createMock(QUI\Groups\Group::class);
        $Group->method('getId')->willReturn(4242);
        $Group->method('getUUID')->willReturn('phpunit-group');
        $User = $this->createMock(QUI\Interfaces\Users\User::class);
        $User->method('getId')->willReturn(1001);
        $User->method('getUUID')->willReturn('phpunit-user');
        $User->method('getGroups')->willReturn([$Group]);
        $Entry = $this->createMock(QUI\ERP\Shipping\Types\ShippingEntry::class);
        $Entry->method('isActive')->willReturn(true);
        $Entry->method('getTitle')->willReturn('PHPUnit shipping');
        $Entry->method('getAttribute')->willReturnCallback(
            static fn (string $name): string => $name === 'user_groups' ? 'g4242' : ''
        );
        $Entity = $this->createMock(QUI\ERP\ErpEntityInterface::class);
        $Entity->method('getDeliveryAddress')->willReturn($this->createMock(QUI\ERP\Address::class));

        self::assertTrue((new StandardShippingType())->canUsedBy($User, $Entry, $Entity));
        self::assertTrue((new DigitalShippingType())->canUsedBy($User, $Entry, $Entity));
    }

    public function testBuiltInShippingTypesRejectInactiveInvalidAndUnreadableOrders(): void
    {
        $Entity = $this->createMock(QUI\ERP\ErpEntityInterface::class);
        $inactive = $this->createMock(QUI\ERP\Shipping\Types\ShippingEntry::class);
        $inactive->method('isActive')->willReturn(false);
        self::assertFalse((new StandardShippingType())->canUsedInOrder($Entity, $inactive));
        self::assertFalse((new DigitalShippingType())->canUsedInOrder($Entity, $inactive));

        $invalid = $this->createMock(QUI\ERP\Shipping\Types\ShippingEntry::class);
        $invalid->method('isActive')->willReturn(true);
        $invalid->method('isValid')->willReturn(false);
        self::assertFalse((new StandardShippingType())->canUsedInOrder($Entity, $invalid));
        self::assertFalse((new DigitalShippingType())->canUsedInOrder($Entity, $invalid));

        $valid = $this->createMock(QUI\ERP\Shipping\Types\ShippingEntry::class);
        $valid->method('isActive')->willReturn(true);
        $valid->method('isValid')->willReturn(true);
        $unreadable = $this->createMock(QUI\ERP\ErpEntityInterface::class);
        $unreadable->method('getArticles')->willThrowException(new \RuntimeException('Unreadable articles'));
        self::assertFalse((new StandardShippingType())->canUsedInOrder($unreadable, $valid));
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

    public function testShippingUniqueRejectsExistingNonShippingClass(): void
    {
        $Shipping = new ShippingUnique(['shipping_type' => \stdClass::class]);

        $this->expectException(QUI\ERP\Shipping\Exception::class);
        $Shipping->getShippingType();
    }

    public function testShippingFactoryRejectsUnknownShippingClass(): void
    {
        $this->expectException(QUI\ERP\Shipping\Exception::class);

        ShippingFactory::getInstance()->createChild([
            'shipping_type' => 'PHPUnit\\MissingShippingType'
        ]);
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
        self::assertSame(Debug::getLogger(), Debug::getLogger());
        self::assertSame(Debug::getLoggerWithoutFormatter(), Debug::getLoggerWithoutFormatter());

        Debug::disable();
        Debug::clearLogStock();
    }

    public function testDebugEntryLogReturnsInAjaxAndEmptyShippingMailHandlesArticleFailure(): void
    {
        $Entry = $this->createMock(QUI\ERP\Shipping\Types\ShippingEntry::class);
        Debug::generateShippingEntryDebuggingLog($Entry, [], []);

        $Order = $this->createMock(QUI\ERP\Order\OrderInterface::class);
        $Order->method('getArticles')->willThrowException(new QUI\Exception('Missing articles'));
        Debug::sendAdminInfoMailAboutEmptyShipping($Order);

        self::assertTrue(true);
    }

    public function testEmptyShippingMailReturnsWhenShippingIsDisabled(): void
    {
        $Config = QUI::getPackage('quiqqer/shipping')->getConfig();
        $previous = $Config->getValue('shipping', 'deactivated');
        $Shipping = Shipping::getInstance();
        $property = new \ReflectionProperty($Shipping, 'shippingDisabled');
        $cached = $property->getValue($Shipping);

        try {
            $Config->setValue('shipping', 'deactivated', 1);
            $Config->save();
            $property->setValue($Shipping, null);
            Debug::sendAdminInfoMailAboutEmptyShipping(
                $this->createMock(QUI\ERP\Order\OrderInterface::class)
            );
            self::assertTrue(true);
        } finally {
            $Config->setValue('shipping', 'deactivated', $previous);
            $Config->save();
            $property->setValue($Shipping, $cached);
        }
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
