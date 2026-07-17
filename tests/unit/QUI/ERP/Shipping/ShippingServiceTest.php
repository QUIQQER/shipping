<?php

namespace QUI\ERP\Shipping;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\ErpEntityInterface;
use QUI\ERP\Products\Utils\PriceFactor;
use QUI\ERP\Shipping\Methods\Digital\ShippingType as DigitalShippingType;
use QUI\ERP\Shipping\Methods\Standard\ShippingType as StandardShippingType;
use QUI\ERP\Shipping\Types\ShippingEntry;
use QUI\Interfaces\Users\User;

require_once __DIR__ . '/TestShippingProvider.php';
require_once __DIR__ . '/TestShippingService.php';

class ShippingServiceTest extends TestCase
{
    public function testShippingPriceFactorLookupFindsOnlyShippingFactors(): void
    {
        $Articles = new ArticleList();
        $Other = new PriceFactor(['identifier' => 'discount-factor', 'value' => 1]);
        $ShippingFactor = new PriceFactor(['identifier' => 'shipping-pricefactor-42', 'value' => 5]);
        $Articles->addPriceFactor($Other);
        $Entity = $this->createMock(ErpEntityInterface::class);
        $Entity->method('getArticles')->willReturn($Articles);

        self::assertNull(Shipping::getInstance()->getShippingPriceFactor($Entity));

        $Articles->addPriceFactor($ShippingFactor);
        $Found = Shipping::getInstance()->getShippingPriceFactor($Entity);
        self::assertNotNull($Found);
        self::assertSame('shipping-pricefactor-42', $Found->getIdentifier());
    }

    public function testVatUsesSingleAndHighestArticleRate(): void
    {
        $Articles = new ArticleList();
        $Articles->addArticle(new Article([
            'id' => 1,
            'unitPrice' => 10,
            'quantity' => 1,
            'vat' => 7
        ]));
        $Entity = $this->createMock(ErpEntityInterface::class);
        $Entity->method('getArticles')->willReturn($Articles);

        self::assertSame(7, (int)Shipping::getInstance()->getVat($Entity));

        $Articles->addArticle(new Article([
            'id' => 2,
            'unitPrice' => 20,
            'quantity' => 1,
            'vat' => 19
        ]));
        self::assertSame(19, (int)Shipping::getInstance()->getVat($Entity));
    }

    public function testShippingTypesAreFilteredAndResolvedFromProviders(): void
    {
        $Shipping = new TestShippingService();
        $types = $Shipping->getShippingTypes();

        self::assertSame(
            [StandardShippingType::class, DigitalShippingType::class],
            array_keys($types)
        );
        self::assertInstanceOf(
            StandardShippingType::class,
            $Shipping->getShippingType(StandardShippingType::class)
        );
        self::assertInstanceOf(
            DigitalShippingType::class,
            $Shipping->getShippingType(DigitalShippingType::class)
        );
    }

    public function testUserShippingRequiresEntityAndFiltersInactiveOrRejectedEntries(): void
    {
        $Shipping = new TestShippingService();
        $User = $this->createMock(User::class);
        $Entity = $this->createMock(ErpEntityInterface::class);
        self::assertSame([], $Shipping->getUserShipping(null, $Entity));
        $inactive = $this->createMock(ShippingEntry::class);
        $rejected = $this->createMock(ShippingEntry::class);
        $accepted = $this->createMock(ShippingEntry::class);

        $inactive->method('isActive')->willReturn(false);
        $inactive->expects(self::never())->method('canUsedBy');
        $rejected->method('isActive')->willReturn(true);
        $rejected->expects(self::once())->method('canUsedBy')->with($User, $Entity)->willReturn(false);
        $accepted->method('isActive')->willReturn(true);
        $accepted->expects(self::once())->method('canUsedBy')->with($User, $Entity)->willReturn(true);
        $Shipping->shippingList = [$inactive, $rejected, $accepted];

        self::assertSame([], $Shipping->getUserShipping($User));
        self::assertSame([2 => $accepted], $Shipping->getUserShipping($User, $Entity));
    }

    public function testConfigurationFlagsAndRuleFieldsAreReadAndCached(): void
    {
        $Config = QUI::getPackage('quiqqer/shipping')->getConfig();
        $previousDisabled = $Config->getValue('shipping', 'deactivated');
        $previousDebug = $Config->getValue('shipping', 'debug');
        $previousFields = $Config->getValue('shipping', 'ruleFields');

        try {
            $Config->setValue('shipping', 'deactivated', 1);
            $Config->setValue('shipping', 'debug', 1);
            $Config->setValue('shipping', 'ruleFields', '100,200');
            $Config->save();

            $Shipping = new TestShippingService();
            self::assertTrue($Shipping->shippingDisabled());
            self::assertTrue($Shipping->shippingDisabled());
            self::assertTrue($Shipping->debuggingEnabled());
            self::assertTrue($Shipping->debuggingEnabled());
            self::assertSame(['100', '200'], $Shipping->getShippingRuleUnitFieldIds());

            $Config->setValue('shipping', 'ruleFields', '');
            $Config->save();
            self::assertSame(
                [QUI\ERP\Products\Handler\Fields::FIELD_WEIGHT],
                $Shipping->getShippingRuleUnitFieldIds()
            );
        } finally {
            $this->restoreConfigValue($Config, 'deactivated', $previousDisabled);
            $this->restoreConfigValue($Config, 'debug', $previousDebug);
            $this->restoreConfigValue($Config, 'ruleFields', $previousFields);
            $Config->save();
        }
    }

    public function testShippingByObjectAttachesDeliveryAddress(): void
    {
        $Address = $this->createMock(QUI\ERP\Address::class);
        $Entry = $this->createMock(ShippingEntry::class);
        $Entity = $this->createMock(QUI\ERP\Order\AbstractOrder::class);
        $Entity->method('getDeliveryAddress')->willReturn($Address);
        $Entity->method('getShipping')->willReturn($Entry);
        $Entry->expects(self::once())->method('setAddress')->with($Address);

        self::assertSame($Entry, (new TestShippingService())->getShippingByObject($Entity));
    }

    public function testShippingByObjectReturnsNullForGenericEntityWithoutShippingApi(): void
    {
        $Entity = $this->createMock(ErpEntityInterface::class);
        $Entity->method('getDeliveryAddress')->willReturn($this->createMock(QUI\ERP\Address::class));

        self::assertNull((new TestShippingService())->getShippingByObject($Entity));
    }

    public function testStatusNotificationStopsForCustomerWithoutEmail(): void
    {
        $Customer = $this->createMock(QUI\ERP\User::class);
        $Customer->method('getAttribute')->with('email')->willReturn('');
        $Customer->method('getUUID')->willReturn('phpunit-customer');
        $Entity = $this->createMock(ErpEntityInterface::class);
        $Entity->method('getCustomer')->willReturn($Customer);
        $Entity->method('getPrefixedNumber')->willReturn('PHPUNIT-1');

        (new TestShippingService())->sendStatusChangeNotification($Entity, 1, 'Ignored');
        self::assertTrue(true);
    }

    public function testVatFallsBackForEmptyArticleLists(): void
    {
        $Articles = new ArticleList();
        $EntityWithoutCustomer = $this->createMock(ErpEntityInterface::class);
        $EntityWithoutCustomer->method('getArticles')->willReturn($Articles);
        $EntityWithoutCustomer->method('getCustomer')->willReturn(null);

        self::assertGreaterThanOrEqual(0, Shipping::getInstance()->getVat($EntityWithoutCustomer));

        $Customer = $this->createMock(QUI\ERP\User::class);
        $EntityWithCustomer = $this->createMock(ErpEntityInterface::class);
        $EntityWithCustomer->method('getArticles')->willReturn($Articles);
        $EntityWithCustomer->method('getCustomer')->willReturn($Customer);

        self::assertGreaterThanOrEqual(0, Shipping::getInstance()->getVat($EntityWithCustomer));
    }

    public function testDeprecatedOrderPriceFactorLookupDelegatesToEntityLookup(): void
    {
        $Articles = new ArticleList();
        $Factor = new PriceFactor(['identifier' => 'shipping-pricefactor-88', 'value' => 8]);
        $Articles->addPriceFactor($Factor);
        $Order = $this->createMock(QUI\ERP\Order\AbstractOrder::class);
        $Order->method('getArticles')->willReturn($Articles);

        self::assertSame(
            'shipping-pricefactor-88',
            Shipping::getInstance()->getShippingPriceFactorByOrder($Order)?->getIdentifier()
        );
    }

    private function restoreConfigValue(object $Config, string $key, mixed $value): void
    {
        if ($value === null) {
            $Config->del('shipping', $key);
            return;
        }

        $Config->setValue('shipping', $key, $value);
    }
}
