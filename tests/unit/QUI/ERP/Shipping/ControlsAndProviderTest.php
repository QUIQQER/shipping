<?php

namespace QUI\ERP\Shipping;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Shipping\FrontendUsers\ShippingAddressSelect;
use QUI\ERP\Shipping\Order\Shipping as ShippingStep;
use QUI\Smarty\Collector;

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

    public function testFrontendAddressEventRendersForRegularUser(): void
    {
        $User = $this->createMock(QUI\Users\User::class);
        $User->method('getAddressList')->willReturn([]);
        $User->method('getAttribute')->willReturn(null);
        $Collector = new Collector();

        EventHandler::onFrontendUsersAddressTop($Collector, $User);

        self::assertStringContainsString('quiqqer-shipping-user-address', $Collector->getContent());
    }

    public function testUserSaveEventReturnsCleanlyWithoutShippingSubmission(): void
    {
        $User = $this->createMock(QUI\Users\User::class);

        EventHandler::onUserSaveBegin($User);

        self::assertTrue(true);
    }

    public function testPriceEventHonorsConfigurationAndVatText(): void
    {
        $Config = QUI::getPackage('quiqqer/shipping')->getConfig();
        $previous = $Config->getValue('shipping', 'showShippingInfoAfterPrice');
        $Collector = new Collector();
        $Price = $this->createMock(QUI\ERP\Products\Controls\Price::class);
        $Price->method('getAttribute')->with('withVatText')->willReturn(true);

        try {
            $Config->setValue('shipping', 'showShippingInfoAfterPrice', 1);
            $Config->save();

            EventHandler::onQuiqqerProductsPriceEnd($Collector, $Price);
            self::assertNotSame('', $Collector->getContent());
        } finally {
            if ($previous === null) {
                $Config->del('shipping', 'showShippingInfoAfterPrice');
            } else {
                $Config->setValue('shipping', 'showShippingInfoAfterPrice', $previous);
            }

            $Config->save();
        }
    }
}
