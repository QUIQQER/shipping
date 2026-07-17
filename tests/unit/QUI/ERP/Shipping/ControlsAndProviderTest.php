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

    public function testUserSaveEventStoresSelectedDeliveryAddress(): void
    {
        $User = $this->createMock(QUI\Users\User::class);
        $Address = $this->createMock(QUI\Users\Address::class);
        $Address->method('getId')->willReturn(123);
        $User->expects(self::once())->method('getAddress')->with(123)->willReturn($Address);
        $User->expects(self::exactly(2))->method('setAttribute')->willReturnCallback(
            static function (string $name, mixed $value) use ($Address): void {
                if ($name === 'quiqqer.delivery.address') {
                    self::assertSame(123, $value);
                } else {
                    self::assertSame('CurrentAddress', $name);
                    self::assertSame($Address, $value);
                }
            }
        );
        $previousStep = $_REQUEST['step'] ?? null;
        $previousAddress = $_REQUEST['shipping-address'] ?? null;

        try {
            $_REQUEST['step'] = 'Customer';
            $_REQUEST['shipping-address'] = 123;
            EventHandler::onUserSaveBegin($User);
        } finally {
            $this->restoreRequestValue('step', $previousStep);
            $this->restoreRequestValue('shipping-address', $previousAddress);
        }
    }

    public function testCustomerDataEventCopiesInvoiceAddressToDeliveryAddress(): void
    {
        $Customer = $this->createMock(QUI\ERP\User::class);
        $Customer->method('getUUID')->willReturn('missing-phpunit-user');
        $InvoiceAddress = $this->createMock(QUI\ERP\Address::class);
        $Order = $this->createMock(QUI\ERP\Order\AbstractOrder::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getInvoiceAddress')->willReturn($InvoiceAddress);
        $Order->expects(self::once())->method('setDeliveryAddress')->with($InvoiceAddress);
        $CustomerData = $this->createMock(QUI\ERP\Order\Controls\OrderProcess\CustomerData::class);
        $CustomerData->method('getOrder')->willReturn($Order);
        $previousAddress = $_REQUEST['shipping-address'] ?? null;

        try {
            $_REQUEST['shipping-address'] = -1;
            EventHandler::onQuiqqerOrderCustomerDataSave($CustomerData);
        } finally {
            $this->restoreRequestValue('shipping-address', $previousAddress);
        }
    }

    public function testCustomerDataEventIgnoresRequestWithoutShippingAddress(): void
    {
        $CustomerData = $this->createMock(QUI\ERP\Order\Controls\OrderProcess\CustomerData::class);
        $previousAddress = $_REQUEST['shipping-address'] ?? null;

        try {
            unset($_REQUEST['shipping-address']);
            EventHandler::onQuiqqerOrderCustomerDataSave($CustomerData);
            self::assertTrue(true);
        } finally {
            $this->restoreRequestValue('shipping-address', $previousAddress);
        }
    }

    public function testCustomerDataEventClearsInvalidDeliveryAddress(): void
    {
        $Customer = $this->createMock(QUI\ERP\User::class);
        $Customer->method('getUUID')->willReturn('missing-phpunit-user');
        $Order = $this->createMock(QUI\ERP\Order\AbstractOrder::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->expects(self::once())->method('clearAddressDelivery');
        $CustomerData = $this->createMock(QUI\ERP\Order\Controls\OrderProcess\CustomerData::class);
        $CustomerData->method('getOrder')->willReturn($Order);
        $previousAddress = $_REQUEST['shipping-address'] ?? null;

        try {
            $_REQUEST['shipping-address'] = PHP_INT_MAX;
            EventHandler::onQuiqqerOrderCustomerDataSave($CustomerData);
        } finally {
            $this->restoreRequestValue('shipping-address', $previousAddress);
        }
    }

    public function testCustomerDataEventStoresValidDeliveryAddress(): void
    {
        $Users = QUI::getUsers();
        $Session = new \ReflectionProperty($Users, 'Session');
        $previousSession = $Session->getValue($Users);
        $Address = $this->createMock(QUI\Users\Address::class);
        $Address->method('getId')->willReturn(91001);
        $Address->method('getUUID')->willReturn('phpunit-address');
        $Address->method('getAttributes')->willReturn([
            'firstname' => 'PHPUnit',
            'lastname' => 'Shipping',
            'street' => 'Test Street',
            'street_no' => '1',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE'
        ]);
        $SessionUser = $this->createMock(QUI\Users\User::class);
        $SessionUser->method('getAddress')->with(91001)->willReturn($Address);
        $Customer = $this->createMock(QUI\ERP\User::class);
        $Customer->method('getUUID')->willReturn('missing-phpunit-user');
        $Order = $this->createMock(QUI\ERP\Order\AbstractOrder::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->expects(self::once())->method('setDeliveryAddress')->with(
            self::callback(static function (QUI\ERP\Address $ErpAddress) use ($Address): bool {
                return $ErpAddress->getId() === $Address->getId()
                    && $ErpAddress->getUUID() === $Address->getUUID();
            })
        );
        $CustomerData = $this->createMock(QUI\ERP\Order\Controls\OrderProcess\CustomerData::class);
        $CustomerData->method('getOrder')->willReturn($Order);
        $previousAddress = $_REQUEST['shipping-address'] ?? null;

        try {
            $Session->setValue($Users, $SessionUser);
            $_REQUEST['shipping-address'] = $Address->getId();
            EventHandler::onQuiqqerOrderCustomerDataSave($CustomerData);
        } finally {
            $Session->setValue($Users, $previousSession);
            $this->restoreRequestValue('shipping-address', $previousAddress);
        }
    }

    public function testCustomerDataOutputAppendsShippingAddressControl(): void
    {
        $User = QUI::getUsers()->getSystemUser();
        $Order = $this->createMock(QUI\ERP\Order\AbstractOrder::class);
        $Order->method('getDeliveryAddress')->willReturn($this->createMock(QUI\ERP\Address::class));
        $Order->method('getShipping')->willReturn(null);
        $Collector = new Collector();

        EventHandler::onOrderProcessCustomerDataEnd($Collector, $User, null, $Order);

        self::assertStringContainsString('quiqqer-shipping-address', $Collector->getContent());
    }

    public function testCheckoutBeforeStopsWhenSessionHasNoSavedDeliveryAddress(): void
    {
        $SessionUser = QUI::getUserBySession();
        $Customer = $this->createMock(QUI\ERP\User::class);
        $Customer->method('getUUID')->willReturn($SessionUser->getUUID());
        $Order = $this->createMock(QUI\ERP\Order\AbstractOrder::class);
        $Order->method('hasDeliveryAddress')->willReturn(false);
        $Order->method('getCustomer')->willReturn($Customer);
        $Checkout = $this->createMock(QUI\ERP\Order\Controls\OrderProcess\Checkout::class);
        $Checkout->method('getOrder')->willReturn($Order);

        EventHandler::onQuiqqerOrderOrderProcessCheckoutOutputBefore($Checkout);
        self::assertTrue(true);
    }

    public function testCheckoutBeforeRestoresSavedDeliveryAddress(): void
    {
        $Users = QUI::getUsers();
        $Session = new \ReflectionProperty($Users, 'Session');
        $previousSession = $Session->getValue($Users);
        $Address = $this->createMock(QUI\Users\Address::class);
        $Address->method('getId')->willReturn(91002);
        $SessionUser = $this->createMock(QUI\Users\User::class);
        $SessionUser->method('getUUID')->willReturn('phpunit-session-user');
        $SessionUser->method('getAttribute')->with('quiqqer.delivery.address')->willReturn(91002);
        $Customer = $this->createMock(QUI\ERP\User::class);
        $Customer->method('getUUID')->willReturn($SessionUser->getUUID());
        $Customer->expects(self::once())->method('getAddress')->with($Address->getId())->willReturn($Address);
        $Order = $this->createMock(QUI\ERP\Order\AbstractOrder::class);
        $Order->method('hasDeliveryAddress')->willReturn(false);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->expects(self::once())->method('setDeliveryAddress')->with($Address);
        $Checkout = $this->createMock(QUI\ERP\Order\Controls\OrderProcess\Checkout::class);
        $Checkout->method('getOrder')->willReturn($Order);

        try {
            $Session->setValue($Users, $SessionUser);
            EventHandler::onQuiqqerOrderOrderProcessCheckoutOutputBefore($Checkout);
        } finally {
            $Session->setValue($Users, $previousSession);
        }
    }

    public function testCheckoutOutputIgnoresGuestCustomer(): void
    {
        $Customer = $this->createMock(QUI\ERP\User::class);
        $Customer->method('getId')->willReturn(6);
        $Order = $this->createMock(QUI\ERP\Order\AbstractOrder::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Checkout = $this->createMock(QUI\ERP\Order\Controls\OrderProcess\Checkout::class);
        $Checkout->method('getOrder')->willReturn($Order);

        EventHandler::onQuiqqerOrderOrderProcessCheckoutOutput($Checkout, 'checkout');
        self::assertTrue(true);
    }

    public function testCheckoutOutputKeepsExistingDeliveryAddress(): void
    {
        $Customer = $this->createMock(QUI\ERP\User::class);
        $Customer->method('getId')->willReturn(1000);
        $Address = $this->createMock(QUI\ERP\Address::class);
        $Address->method('getUUID')->willReturn('phpunit-address');
        $Order = $this->createMock(QUI\ERP\Order\AbstractOrder::class);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getDeliveryAddress')->willReturn($Address);
        $Checkout = $this->createMock(QUI\ERP\Order\Controls\OrderProcess\Checkout::class);
        $Checkout->method('getOrder')->willReturn($Order);

        EventHandler::onQuiqqerOrderOrderProcessCheckoutOutput($Checkout, 'checkout');
        self::assertTrue(true);
    }

    public function testCheckoutEventsIgnoreMissingOrder(): void
    {
        $Checkout = $this->createMock(QUI\ERP\Order\Controls\OrderProcess\Checkout::class);
        $Checkout->method('getOrder')->willReturn(null);

        EventHandler::onQuiqqerOrderOrderProcessCheckoutOutputBefore($Checkout);
        EventHandler::onQuiqqerOrderOrderProcessCheckoutOutput($Checkout, 'checkout');

        self::assertTrue(true);
    }

    public function testBasketAndPaymentEventsIgnoreOrderWithoutShipping(): void
    {
        $Order = $this->createMock(QUI\ERP\Order\AbstractOrder::class);
        $Order->method('getShipping')->willReturn(null);
        $Products = $this->createMock(QUI\ERP\Products\Product\ProductList::class);
        $Payment = $this->createMock(QUI\ERP\Accounting\Payments\Types\Payment::class);

        EventHandler::onQuiqqerOrderBasketToOrderEnd(null, $Order, $Products);
        EventHandler::onQuiqqerPaymentCanUsedInOrder($Payment, $Order);

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

    private function restoreRequestValue(string $key, mixed $value): void
    {
        if ($value === null) {
            unset($_REQUEST[$key]);
            return;
        }

        $_REQUEST[$key] = $value;
    }
}
