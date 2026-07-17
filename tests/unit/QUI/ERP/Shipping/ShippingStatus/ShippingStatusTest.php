<?php

namespace QUI\ERP\Shipping\ShippingStatus;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use QUI;
use QUI\ERP\Shipping\Shipping;

class ShippingStatusTest extends TestCase
{
    protected function setUp(): void
    {
        (new ReflectionProperty(Handler::getInstance(), 'list'))->setValue(Handler::getInstance(), null);
    }

    public function testHandlerReadsConfiguredStatusesAndCachesThem(): void
    {
        $Handler = Handler::getInstance();
        $list = $Handler->getList();

        self::assertIsArray($list);
        self::assertSame($list, $Handler->getList());
        self::assertSame($list, $Handler->refreshList());
        self::assertInstanceOf(StatusUnknown::class, $Handler->getShippingStatus(0));

        foreach ($Handler->getShippingStatusList() as $Status) {
            self::assertTrue($Handler->exists($Status->getId()));
            self::assertSame($list[$Status->getId()], $Status->getColor());
            self::assertSame($Status->getId(), $Status->toArray()['id']);
            self::assertIsString($Status->getTitle());
        }
    }

    public function testStatusFactoryCalculatesUnusedNextId(): void
    {
        $list = Handler::getInstance()->getList();
        $expected = $list === [] ? 1 : max(array_map('intval', array_keys($list))) + 1;

        self::assertSame($expected, Factory::getInstance()->getNextId());
    }

    public function testUnknownConfiguredStatusThrows(): void
    {
        $ids = array_map('intval', array_keys(Handler::getInstance()->getList()));
        $missing = $ids === [] ? 1 : max($ids) + 1000;

        $this->expectException(Exception::class);
        Handler::getInstance()->getShippingStatus($missing);
    }

    public function testNotificationServicesReturnWhenCustomerHasNoEmail(): void
    {
        $Customer = $this->createMock(QUI\ERP\User::class);
        $Customer->method('getAttribute')->with('email')->willReturn(null);
        $Customer->method('getUUID')->willReturn('phpunit-customer');
        $Entity = $this->createMock(QUI\ERP\ErpEntityInterface::class);
        $Entity->method('getCustomer')->willReturn($Customer);
        $Entity->method('getPrefixedNumber')->willReturn('ENTITY-1');

        Handler::getInstance()->sendStatusChangeNotification($Entity, 0);
        Shipping::getInstance()->sendStatusChangeNotification($Entity, 0);

        self::assertTrue(true);
    }
}
