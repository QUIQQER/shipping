<?php

namespace QUI\ERP\Shipping;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Shipping\FrontendUsers\ShippingAddressSelect;
use QUI\ERP\Shipping\Order\Shipping as ShippingStep;

class ControlsAndProviderTest extends TestCase
{
    public function testShippingAddressSelectRendersEmptyAndUserAddressLists(): void
    {
        $Empty = new ShippingAddressSelect();
        $WithUser = new ShippingAddressSelect([
            'User' => QUI::getUsers()->getSystemUser()
        ]);

        self::assertStringContainsString('quiqqer-shipping-user-address', $Empty->create());
        self::assertStringContainsString('quiqqer-shipping-user-address', $WithUser->create());
    }

    public function testOrderingStepProvidesStableMetadata(): void
    {
        $Step = new ShippingStep();

        self::assertSame('Shipping', $Step->getName());
        self::assertSame('fa-truck', $Step->getIcon());
    }

    public function testOrderProcessProviderDisplayIsIntentionallyEmpty(): void
    {
        $Order = $this->createMock(QUI\ERP\Order\AbstractOrder::class);

        self::assertSame('', (new OrderProcessProvider())->getDisplay($Order));
    }

    public function testOrderProcessProviderAppendsShippingStep(): void
    {
        $Steps = new QUI\ERP\Order\Utils\OrderProcessSteps();
        $Process = new QUI\ERP\Order\OrderProcess();

        (new OrderProcessProvider())->initSteps($Steps, $Process);

        self::assertGreaterThanOrEqual(1, $Steps->count());
        self::assertInstanceOf(ShippingStep::class, $Steps->get(0));
    }

    public function testAdminFooterRegistersShippingBackendScript(): void
    {
        ob_start();
        EventHandler::onAdminLoadFooter();
        $output = (string)ob_get_clean();

        self::assertStringContainsString('quiqqer/shipping/bin/backend/load.js', $output);
        self::assertStringStartsWith('<script', $output);
    }
}
