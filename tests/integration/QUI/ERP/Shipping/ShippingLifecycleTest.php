<?php

namespace QUI\ERP\Shipping;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Order\Factory as OrderFactory;
use QUI\ERP\Order\Handler as OrderHandler;
use QUI\ERP\Shipping\Rules\Factory as RuleFactory;
use QUI\ERP\Shipping\Exception as ShippingException;
use QUI\ERP\Shipping\Methods\Digital\ShippingType as DigitalShippingType;
use QUI\ERP\Shipping\Methods\Standard\ShippingType as StandardShippingType;
use QUI\ERP\Shipping\Order\Shipping as ShippingStep;
use QUI\ERP\Shipping\Order\ShippingAddress;
use QUI\ERP\Order\Controls\OrderProcess\Checkout;
use QUI\ERP\Products\Product\ProductList;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\Smarty\Collector;
use QUI\ERP\Shipping\Types\Factory as ShippingFactory;
use QUI\ERP\Shipping\Types\ShippingEntry;
use ReflectionProperty;
use Throwable;
use QUI\ERP\Shipping\Tests\Stubs\AlwaysAvailableShippingType;

class ShippingLifecycleTest extends TestCase
{
    private ?int $shippingId = null;
    private array $additionalShippingIds = [];
    private ?int $ruleId = null;
    private array $additionalRuleIds = [];
    private ?string $orderHash = null;
    private mixed $previousSessionUser = null;

    protected function setUp(): void
    {
        try {
            $this->getConnection()->executeQuery('SELECT 1')->free();
        } catch (Throwable $Exception) {
            self::markTestSkipped('QUIQQER database is not available: ' . $Exception->getMessage());
        }

        $Users = QUI::getUsers();
        $Session = new ReflectionProperty($Users, 'Session');
        $this->previousSessionUser = $Session->getValue($Users);
        $Session->setValue($Users, $Users->getSystemUser());

        $this->insertRule();
        $this->insertShippingEntry();
    }

    protected function tearDown(): void
    {
        $Connection = $this->getConnection();

        if ($this->orderHash !== null) {
            $Connection->delete(OrderHandler::getInstance()->table(), ['hash' => $this->orderHash]);
        }

        if ($this->shippingId !== null) {
            $Connection->delete(ShippingFactory::getInstance()->getDataBaseTableName(), ['id' => $this->shippingId]);
            QUI\Translator::delete('quiqqer/shipping', 'shipping.' . $this->shippingId . '.title');
            QUI\Translator::delete('quiqqer/shipping', 'shipping.' . $this->shippingId . '.description');
            QUI\Translator::delete('quiqqer/shipping', 'shipping.' . $this->shippingId . '.workingTitle');
        }

        foreach ($this->additionalShippingIds as $shippingId) {
            $Connection->delete(ShippingFactory::getInstance()->getDataBaseTableName(), ['id' => $shippingId]);
            QUI\Translator::delete('quiqqer/shipping', 'shipping.' . $shippingId . '.title');
            QUI\Translator::delete('quiqqer/shipping', 'shipping.' . $shippingId . '.description');
            QUI\Translator::delete('quiqqer/shipping', 'shipping.' . $shippingId . '.workingTitle');
        }

        if ($this->ruleId !== null) {
            $Connection->delete(RuleFactory::getInstance()->getDataBaseTableName(), ['id' => $this->ruleId]);
            QUI\Translator::delete('quiqqer/shipping', 'shipping.' . $this->ruleId . '.rule.title');
            QUI\Translator::delete('quiqqer/shipping', 'shipping.' . $this->ruleId . '.rule.workingTitle');
        }

        foreach ($this->additionalRuleIds as $ruleId) {
            $Connection->delete(RuleFactory::getInstance()->getDataBaseTableName(), ['id' => $ruleId]);
            QUI\Translator::delete('quiqqer/shipping', 'shipping.' . $ruleId . '.rule.title');
            QUI\Translator::delete('quiqqer/shipping', 'shipping.' . $ruleId . '.rule.workingTitle');
        }

        if ($this->previousSessionUser !== null) {
            (new ReflectionProperty(QUI::getUsers(), 'Session'))->setValue(
                QUI::getUsers(),
                $this->previousSessionUser
            );
        }
    }

    public function testCrudFactoriesLoadFilterAndPersistShippingConfiguration(): void
    {
        $RuleFactory = RuleFactory::getInstance();
        $Rule = $RuleFactory->getChild($this->ruleId);

        self::assertSame($this->ruleId, $Rule->getId());
        self::assertTrue($Rule->isActive());
        self::assertTrue($Rule->isValid());
        self::assertSame(5.5, $Rule->getDiscount());
        self::assertSame(10, $Rule->getPriority());
        self::assertSame(RuleFactory::DISCOUNT_TYPE_ABS, $Rule->getDiscountType());
        self::assertFalse($Rule->noRulesAfter());
        self::assertSame(['weight' => ['from' => 0, 'until' => 20]], $Rule->getUnitTerms());
        self::assertArrayHasKey('discount', $Rule->toArray());
        self::assertSame(1, $RuleFactory->countChildren(['where' => ['id' => $this->ruleId]]));
        self::assertTrue($Rule->canUsedBy(QUI::getUsers()->getSystemUser()));
        self::assertFalse($Rule->canUsedWithAddress());
        self::assertTrue($Rule->canUsedIn());

        $language = QUI::getLocale()->getCurrent();
        $Rule->setTitle([$language => 'PHPUnit rule title']);
        $Rule->setWorkingTitle([$language => 'PHPUnit rule working title']);
        self::assertNotSame('', $Rule->getTitle());

        $Factory = ShippingFactory::getInstance();
        $Entry = $Factory->getChild($this->shippingId);

        self::assertInstanceOf(ShippingEntry::class, $Entry);
        self::assertSame($this->shippingId, $Entry->getId());
        self::assertTrue($Entry->isActive());
        self::assertInstanceOf(AlwaysAvailableShippingType::class, $Entry->getShippingType());
        self::assertCount(1, $Entry->getShippingRules());
        self::assertSame(5.5, $Entry->getPrice());
        self::assertNotSame('', $Entry->getTitle());
        self::assertNotSame('', $Entry->getDescription());
        self::assertNotSame('', $Entry->getWorkingTitle());
        self::assertSame('/phpunit-shipping.svg', $Entry->getIcon());
        self::assertTrue($Entry->isValid());
        self::assertJson($Entry->toJSON());
        self::assertSame(AlwaysAvailableShippingType::class, $Entry->toArray()['shipping_type']);

        $Entry->setTitle([$language => 'PHPUnit shipping title']);
        $Entry->setDescription([$language => 'PHPUnit shipping description']);
        $Entry->setWorkingTitle([$language => 'PHPUnit shipping working title']);
        self::assertNotSame('', $Entry->getTitle());
        self::assertNotSame('', $Entry->getDescription());
        self::assertNotSame('', $Entry->getWorkingTitle());
        $Entry->setIcon('not-a-media-url');
        self::assertSame('', (string)$Entry->getAttribute('icon'));

        $Entry->deactivate();
        self::assertFalse($Entry->isActive());
        self::assertFalse($Entry->isValid());
        $Entry->activate();
        self::assertTrue($Entry->isActive());

        $Entry->addShippingRuleId($this->ruleId);
        self::assertCount(1, json_decode((string)$Entry->getAttribute('shipping_rules'), true));
        $Entry->addShippingRule($Rule);
        self::assertCount(1, json_decode((string)$Entry->getAttribute('shipping_rules'), true));
        $Entry->removeIcon();
        self::assertFalse($Entry->getAttribute('icon'));

        $Address = QUI::getUsers()->getSystemUser()->getStandardAddress();
        $Entry->setAddress($Address);
        self::assertSame($Address, $Entry->getAddress());

        $Shipping = Shipping::getInstance();
        self::assertSame($this->shippingId, $Shipping->getShippingEntry($this->shippingId)->getId());
        self::assertContains(
            $this->shippingId,
            array_map(static fn (ShippingEntry $Item): int => $Item->getId(), $Shipping->getShippingList())
        );
        self::assertNotSame('', $Shipping->getHost());
        self::assertIsArray($Shipping->getShippingProviders());
        self::assertIsArray($Shipping->getShippingTypes());
        self::assertIsBool($Shipping->shippingDisabled());
        self::assertIsBool($Shipping->debuggingEnabled());
        self::assertNotEmpty($Shipping->getShippingRuleUnitFieldIds());
        self::assertSame([], $Shipping->getUserShipping(QUI::getUsers()->getSystemUser()));
        self::assertNull($Shipping->getShippingByOrderId(PHP_INT_MAX));

        self::assertSame('shipping', $Factory->getDataBaseTableName());
        self::assertSame(ShippingEntry::class, $Factory->getChildClass());
        self::assertContains('shipping_type', $Factory->getChildAttributes());
        self::assertSame('shipping_rules', $RuleFactory->getDataBaseTableName());
        self::assertContains('discount_type', $RuleFactory->getChildAttributes());
    }

    public function testRuleStateTransitionsArePersistedThroughDbal(): void
    {
        $Rule = RuleFactory::getInstance()->getChild($this->ruleId);
        $Connection = $this->getConnection();
        $table = RuleFactory::getInstance()->getDataBaseTableName();

        $Connection->update($table, ['active' => 0], ['id' => $this->ruleId]);
        $Rule->refresh();
        self::assertFalse($Rule->isActive());
        self::assertFalse($Rule->isValid());
        self::assertFalse($Rule->canUsedBy(QUI::getUsers()->getSystemUser()));

        $Connection->update(
            $table,
            ['active' => 1, 'date_from' => date('Y-m-d H:i:s', strtotime('+2 days'))],
            ['id' => $this->ruleId]
        );
        $Rule->refresh();
        self::assertFalse($Rule->isValid());
        self::assertFalse($Rule->canUsedBy(QUI::getUsers()->getSystemUser()));

        $SystemUser = QUI::getUsers()->getSystemUser();
        $Connection->update(
            $table,
            [
                'date_from' => null,
                'date_until' => null,
                'user_groups' => 'u' . $SystemUser->getId()
            ],
            ['id' => $this->ruleId]
        );
        $Rule->refresh();
        self::assertTrue($Rule->canUsedBy($SystemUser));

        $Connection->update(
            $table,
            ['user_groups' => 'u-user-that-does-not-exist'],
            ['id' => $this->ruleId]
        );
        $Rule->refresh();
        self::assertFalse($Rule->canUsedBy($SystemUser));

        $Address = new QUI\ERP\Address([
            'id' => 91008,
            'firstname' => 'PHPUnit',
            'lastname' => 'Shipping',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE'
        ]);
        self::assertTrue($Rule->canUsedWithAddress($Address));

        $Connection->update(
            $table,
            ['date_from' => null, 'date_until' => date('Y-m-d H:i:s', strtotime('-2 days'))],
            ['id' => $this->ruleId]
        );
        $Rule->refresh();
        self::assertFalse($Rule->isValid());
        self::assertFalse($Rule->canUsedBy(QUI::getUsers()->getSystemUser()));
    }

    public function testRuleActivationLifecycleWorksWithPersistedAttributes(): void
    {
        $Rule = RuleFactory::getInstance()->getChild($this->ruleId);

        $Rule->deactivate();
        self::assertFalse($Rule->isActive());

        $Rule->activate();
        self::assertTrue($Rule->isActive());
    }

    public function testShippingServiceRejectsUnknownTypesAndEntries(): void
    {
        $Shipping = Shipping::getInstance();

        foreach (['', 'Not\\A\\ShippingType'] as $type) {
            try {
                $Shipping->getShippingType($type);
                self::fail('Unknown shipping type must throw.');
            } catch (ShippingException) {
                self::assertTrue(true);
            }
        }

        $this->expectException(ShippingException::class);
        $Shipping->getShippingEntry(PHP_INT_MAX);
    }

    public function testDefaultPriceFactorUsesConfiguredShippingPrice(): void
    {
        $Config = QUI::getPackage('quiqqer/shipping')->getConfig();
        $previous = $Config->getValue('shipping', 'defaultShippingPrice');

        try {
            $Config->setValue('shipping', 'defaultShippingPrice', '12.50');
            $Config->save();

            $Factor = Shipping::getInstance()->getDefaultPriceFactor();
            self::assertSame('shipping-pricefactor-default', $Factor->getIdentifier());
            self::assertSame(12.5, $Factor->getValue());
            self::assertSame(12.5, $Factor->getNettoSum());
            self::assertGreaterThanOrEqual(0, $Factor->getVat());
        } finally {
            if ($previous === null) {
                $Config->del('shipping', 'defaultShippingPrice');
            } else {
                $Config->setValue('shipping', 'defaultShippingPrice', $previous);
            }

            $Config->save();
        }
    }

    public function testFactoriesNormalizeInputAndPersistThroughDbal(): void
    {
        $language = QUI::getLocale()->getCurrent();
        $Rule = RuleFactory::getInstance()->createChild([
            'active' => '1',
            'title' => [$language => 'PHPUnit normalized rule'],
            'workingTitle' => [$language => 'PHPUnit normalized working title'],
            'priority' => '12',
            'purchase_quantity_from' => '2',
            'purchase_quantity_until' => '8',
            'discount' => '7.5',
            'discount_type' => 'PERCENTAGE',
            'unit_terms' => [['id' => 1, 'value' => 2]],
            'articles_only' => true,
            'no_rule_after' => true,
            'not_allowed' => 'discard me'
        ]);
        $this->additionalRuleIds[] = $Rule->getId();

        self::assertFalse($Rule->isActive());
        self::assertSame(12, $Rule->getPriority());
        self::assertSame(7.5, $Rule->getDiscount());
        self::assertSame(RuleFactory::DISCOUNT_TYPE_PERCENTAGE, $Rule->getDiscountType());
        self::assertTrue($Rule->noRulesAfter());
        self::assertIsArray($Rule->getUnitTerms());
        self::assertFalse($Rule->existsAttribute('not_allowed'));
        self::assertSame(
            1,
            RuleFactory::getInstance()->countChildren(['where' => ['id' => $Rule->getId()]])
        );

        $Entry = ShippingFactory::getInstance()->createChild([
            'active' => '1',
            'priority' => '15',
            'shipping_type' => AlwaysAvailableShippingType::class
        ]);
        $this->additionalShippingIds[] = $Entry->getId();

        self::assertFalse($Entry->isActive());
        self::assertSame(0, (int)$Entry->getAttribute('priority'));
        self::assertInstanceOf(AlwaysAvailableShippingType::class, $Entry->getShippingType());
        self::assertSame(
            1,
            ShippingFactory::getInstance()->countChildren(['where' => ['id' => $Entry->getId()]])
        );
    }

    public function testBuiltInShippingTypesCoverOrderAndUserRestrictions(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Order = OrderFactory::getInstance()->create($SystemUser, false, null, uniqid('shipping-types-', true));
        $this->orderHash = $Order->getUUID();
        $Order->setCustomer($SystemUser);
        $Order->getArticles()->addArticle(new Article([
            'id' => 'PHPUNIT-DISCOUNT',
            'articleNo' => 'PHPUNIT-DISCOUNT',
            'title' => 'Non-product article',
            'unitPrice' => 0,
            'quantity' => 1,
            'vat' => 0
        ]));

        $Digital = $this->insertAdditionalShippingEntry(DigitalShippingType::class);
        $Standard = $this->insertAdditionalShippingEntry(StandardShippingType::class);
        $DigitalType = new DigitalShippingType();
        $StandardType = new StandardShippingType();

        self::assertTrue($DigitalType->canUsedInOrder($Order, $Digital));
        self::assertFalse($StandardType->canUsedInOrder($Order, $Standard));
        self::assertTrue($DigitalType->canUsedBy($SystemUser, $Digital, $Order));
        self::assertTrue($StandardType->canUsedBy($SystemUser, $Standard, $Order));

        $this->getConnection()->update(
            ShippingFactory::getInstance()->getDataBaseTableName(),
            ['articles' => '123456'],
            ['id' => $Digital->getId()]
        );
        $Digital->refresh();
        $NumericOrder = OrderFactory::getInstance()->create(
            $SystemUser,
            false,
            null,
            uniqid('shipping-digital-', true)
        );
        $NumericOrder->setCustomer($SystemUser);
        $NumericOrder->getArticles()->addArticle(new Article([
            'id' => 123456,
            'articleNo' => 'SHIPPING-DIGITAL',
            'title' => 'Missing product fixture',
            'unitPrice' => 1,
            'quantity' => 1,
            'vat' => 19
        ]));
        self::assertTrue($DigitalType->canUsedInOrder($NumericOrder, $Digital));

        $userRestriction = 'u' . $SystemUser->getId();
        $this->getConnection()->update(
            ShippingFactory::getInstance()->getDataBaseTableName(),
            ['user_groups' => $userRestriction],
            ['id' => $Digital->getId()]
        );
        $Digital->refresh();
        self::assertTrue($DigitalType->canUsedBy($SystemUser, $Digital, $Order));

        $this->getConnection()->update(
            ShippingFactory::getInstance()->getDataBaseTableName(),
            ['user_groups' => 'u-user-that-does-not-exist'],
            ['id' => $Digital->getId()]
        );
        $Digital->refresh();
        self::assertFalse($DigitalType->canUsedBy($SystemUser, $Digital, $Order));

        $this->getConnection()->update(
            ShippingFactory::getInstance()->getDataBaseTableName(),
            ['active' => 0],
            ['id' => $Digital->getId()]
        );
        $Digital->refresh();
        self::assertFalse($DigitalType->canUsedInOrder($Order, $Digital));
        self::assertFalse($DigitalType->canUsedBy($SystemUser, $Digital, $Order));
    }

    public function testRuleConstraintsRunAgainstPersistedOrderData(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Order = OrderFactory::getInstance()->create($SystemUser, false, null, uniqid('shipping-rules-', true));
        $this->orderHash = $Order->getUUID();
        $Order->setCustomer($SystemUser);
        $Order->setDeliveryAddress([
            'id' => 91006,
            'firstname' => 'PHPUnit',
            'lastname' => 'Shipping',
            'street_no' => '1',
            'street' => 'Test Street',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE'
        ]);
        $Order->getArticles()->addArticle(new Article([
            'id' => 123456789,
            'articleNo' => 'SHIPPING-RULE-1',
            'title' => 'Rule article',
            'unitPrice' => 10,
            'quantity' => 1,
            'vat' => 19
        ]));
        $Order->getArticles()->calc();

        $Rule = RuleFactory::getInstance()->getChild($this->ruleId);
        $table = RuleFactory::getInstance()->getDataBaseTableName();

        self::assertTrue($Rule->canUsedIn($Order));

        $this->updateRule(['articles' => '999'], $Rule, $table);
        self::assertFalse($Rule->canUsedIn($Order));

        $this->updateRule(['articles' => '123456789'], $Rule, $table);
        self::assertTrue($Rule->canUsedIn($Order));

        $Order->getArticles()->addArticle(new Article([
            'id' => 'PHPUNIT-SURCHARGE',
            'articleNo' => 'SHIPPING-RULE-2',
            'title' => 'Second rule article',
            'unitPrice' => 2,
            'quantity' => 1,
            'vat' => 19
        ]));
        $Order->getArticles()->calc();
        $this->updateRule(['articles_only' => 1], $Rule, $table);
        self::assertFalse($Rule->canUsedIn($Order));

        $this->updateRule([
            'articles' => null,
            'articles_only' => 0,
            'purchase_value_from' => 9999,
            'purchase_value_until' => null
        ], $Rule, $table);
        self::assertFalse($Rule->canUsedIn($Order));

        $this->updateRule([
            'purchase_value_from' => null,
            'purchase_value_until' => 1
        ], $Rule, $table);
        self::assertFalse($Rule->canUsedIn($Order));

        $this->updateRule([
            'purchase_value_until' => null,
            'unit_terms' => json_encode([[
                'id' => 1,
                'unit' => 'kg',
                'value' => 1,
                'value2' => 5,
                'term' => 'gt',
                'term2' => 'lt'
            ]], JSON_THROW_ON_ERROR)
        ], $Rule, $table);
        self::assertTrue($Rule->canUsedIn($Order));
    }

    public function testOrderingStepRendersValidatesAndSavesShipping(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Order = OrderFactory::getInstance()->create($SystemUser, false, null, uniqid('shipping-step-', true));
        $this->orderHash = $Order->getUUID();
        $Order->setCustomer($SystemUser);
        $Order->setDeliveryAddress([
            'id' => 91007,
            'firstname' => 'PHPUnit',
            'lastname' => 'Shipping',
            'street_no' => '1',
            'street' => 'Test Street',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE'
        ]);
        $Order->getArticles()->addArticle(new Article([
            'id' => 987654321,
            'articleNo' => 'SHIPPING-STEP',
            'title' => 'Shipping step article',
            'unitPrice' => 10,
            'quantity' => 1,
            'vat' => 19
        ]));
        $Order->getArticles()->calc();

        $Step = new ShippingStep([
            'Order' => $Order,
            'shipping' => $this->shippingId
        ]);
        $previousShippingRequest = $_REQUEST['shipping'] ?? null;

        try {
            $_REQUEST['shipping'] = $this->shippingId;
            $Step->save();
            $Step->validate();

            self::assertSame($this->shippingId, $Order->getShipping()?->getId());
            self::assertStringContainsString('quiqqer-order-step-shipping', $Step->create());

            $AddressControl = new ShippingAddress([
                'User' => $SystemUser,
                'Order' => $Order
            ]);
            self::assertNotSame('', $AddressControl->create());

            $AddressCollector = new Collector();
            EventHandler::onOrderProcessCustomerDataEnd(
                $AddressCollector,
                $SystemUser,
                $Order->getDeliveryAddress(),
                $Order
            );
            self::assertNotSame('', $AddressCollector->getContent());

            $Checkout = new Checkout(['Order' => $Order]);
            EventHandler::onQuiqqerOrderOrderProcessCheckoutOutputBefore($Checkout);
            EventHandler::onQuiqqerOrderOrderProcessCheckoutOutput($Checkout, 'checkout');
            EventHandler::onTemplateGetHeader();
            EventHandler::onPackageSetup(QUI::getPackage('quiqqer/core'));

            $Entry = ShippingFactory::getInstance()->getChild($this->shippingId);
            $Entry->setErpEntity($Order);

            $Products = new ProductList([], $SystemUser);
            EventHandler::onQuiqqerOrderBasketToOrderEnd(null, $Order, $Products);
            self::assertGreaterThanOrEqual(1, $Products->getPriceFactors()->count());

            $Payment = $this->createMock(Payment::class);
            EventHandler::onQuiqqerPaymentCanUsedInOrder($Payment, $Order);

            $Order->getArticles()->addPriceFactor($Entry->toPriceFactor(null, $Order));
            $updateData = [];
            EventHandler::onQuiqqerOrderUpdateBegin($Order, $updateData);
            EventHandler::onQuiqqerCustomerChange($Order);
        } finally {
            if ($previousShippingRequest === null) {
                unset($_REQUEST['shipping']);
            } else {
                $_REQUEST['shipping'] = $previousShippingRequest;
            }
        }
    }

    public function testShippingPriceCombinesAbsolutePercentageAndOrderPercentageRules(): void
    {
        $percentageRuleId = $this->insertAdditionalRule(
            10,
            RuleFactory::DISCOUNT_TYPE_PERCENTAGE,
            20
        );
        $orderPercentageRuleId = $this->insertAdditionalRule(
            10,
            RuleFactory::DISCOUNT_TYPE_PC_ORDER,
            30
        );
        $this->getConnection()->update(
            ShippingFactory::getInstance()->getDataBaseTableName(),
            ['shipping_rules' => json_encode([
                $this->ruleId,
                $percentageRuleId,
                $orderPercentageRuleId
            ], JSON_THROW_ON_ERROR)],
            ['id' => $this->shippingId]
        );

        $SystemUser = QUI::getUsers()->getSystemUser();
        $Order = OrderFactory::getInstance()->create($SystemUser, false, null, uniqid('shipping-price-', true));
        $this->orderHash = $Order->getUUID();
        $Order->setCustomer($SystemUser);
        $Order->setDeliveryAddress([
            'id' => 91009,
            'firstname' => 'PHPUnit',
            'lastname' => 'Shipping',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE'
        ]);
        $Order->getArticles()->addArticle(new Article([
            'id' => 76543210,
            'articleNo' => 'SHIPPING-PRICE',
            'title' => 'Shipping price article',
            'unitPrice' => 100,
            'quantity' => 1,
            'vat' => 19
        ]));
        $Order->getArticles()->calc();

        $Entry = ShippingFactory::getInstance()->getChild($this->shippingId);
        $Entry->setErpEntity($Order);
        $rules = $Entry->getShippingRules();

        self::assertSame(
            [$this->ruleId, $percentageRuleId, $orderPercentageRuleId],
            array_map(static fn ($Rule): int => $Rule->getId(), $rules)
        );
        self::assertSame(16.5, $Entry->getPrice());
        self::assertSame(16.5, $Entry->toPriceFactor(null, $Order)->getValue());
    }

    public function testShippingSurvivesOrderPersistenceAndReload(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Entry = ShippingFactory::getInstance()->getChild($this->shippingId);
        $Order = OrderFactory::getInstance()->create($SystemUser, false, null, uniqid('shipping-process-', true));
        $this->orderHash = $Order->getUUID();

        $Order->setCustomer($SystemUser);
        $Order->getArticles()->addArticle(new Article([
            'id' => 1,
            'articleNo' => 'SHIPPING-PHPUNIT',
            'title' => 'Shipping lifecycle article',
            'unitPrice' => 10,
            'quantity' => 1,
            'vat' => 19
        ]));
        $Order->getArticles()->calc();
        (new ReflectionProperty($Order, 'shippingId'))->setValue($Order, $Entry->getId());
        $Entry->setErpEntity($Order);

        self::assertTrue($Entry->canUsedBy($SystemUser, $Order));
        self::assertTrue($Entry->canUsedInErpEntity($Order));
        self::assertSame(19, (int)Shipping::getInstance()->getVat($Order));
        self::assertStringContainsString('shipping-pricefactor-', $Entry->toPriceFactor()->getIdentifier());
        self::assertNotSame('', $Entry->getPriceDisplay());

        $Order->update($SystemUser);

        $Reloaded = OrderHandler::getInstance()->getOrderByHash($this->orderHash);
        $StoredShipping = Shipping::getInstance()->getShippingByObject($Reloaded);

        self::assertInstanceOf(ShippingEntry::class, $StoredShipping);
        self::assertSame($this->shippingId, (int)$StoredShipping->getId());
        self::assertSame(5.5, $StoredShipping->getPrice());
        self::assertInstanceOf(AlwaysAvailableShippingType::class, $StoredShipping->getShippingType());
        self::assertSame(
            $this->shippingId,
            Shipping::getInstance()->getShippingByOrderId($Reloaded->getId())?->getId()
        );
    }

    private function insertRule(): void
    {
        $Connection = $this->getConnection();
        $Connection->insert(RuleFactory::getInstance()->getDataBaseTableName(), [
            'active' => 1,
            'discount' => 5.5,
            'discount_type' => RuleFactory::DISCOUNT_TYPE_ABS,
            'unit_terms' => json_encode(['weight' => ['from' => 0, 'until' => 20]], JSON_THROW_ON_ERROR),
            'priority' => 10,
            'no_rule_after' => 0,
            'date_from' => null,
            'date_until' => null,
            'purchase_quantity_from' => 0,
            'purchase_quantity_until' => 0,
            'purchase_value_from' => null,
            'purchase_value_until' => null,
            'areas' => null,
            'categories' => null,
            'user_groups' => null,
            'articles' => null,
            'articles_only' => 0
        ]);

        $this->ruleId = (int)$Connection->lastInsertId();
    }

    private function insertShippingEntry(): void
    {
        $Connection = $this->getConnection();
        $Connection->insert(ShippingFactory::getInstance()->getDataBaseTableName(), [
            'active' => 1,
            'shipping_type' => AlwaysAvailableShippingType::class,
            'icon' => '',
            'shipping_rules' => json_encode([$this->ruleId], JSON_THROW_ON_ERROR),
            'priority' => 10,
            'areas' => null,
            'articles' => null,
            'categories' => null,
            'user_groups' => null,
            'payments' => null
        ]);

        $this->shippingId = (int)$Connection->lastInsertId();
    }

    private function insertAdditionalShippingEntry(string $shippingType): ShippingEntry
    {
        $Connection = $this->getConnection();
        $Connection->insert(ShippingFactory::getInstance()->getDataBaseTableName(), [
            'active' => 1,
            'shipping_type' => $shippingType,
            'icon' => '',
            'shipping_rules' => null,
            'priority' => 20,
            'areas' => null,
            'articles' => null,
            'categories' => null,
            'user_groups' => null,
            'payments' => null
        ]);

        $shippingId = (int)$Connection->lastInsertId();
        $this->additionalShippingIds[] = $shippingId;

        return ShippingFactory::getInstance()->getChild($shippingId);
    }

    private function insertAdditionalRule(float $discount, int $discountType, int $priority): int
    {
        $Connection = $this->getConnection();
        $Connection->insert(RuleFactory::getInstance()->getDataBaseTableName(), [
            'active' => 1,
            'discount' => $discount,
            'discount_type' => $discountType,
            'unit_terms' => null,
            'priority' => $priority,
            'no_rule_after' => 0,
            'date_from' => null,
            'date_until' => null,
            'purchase_quantity_from' => 0,
            'purchase_quantity_until' => 0,
            'purchase_value_from' => null,
            'purchase_value_until' => null,
            'areas' => null,
            'categories' => null,
            'user_groups' => null,
            'articles' => null,
            'articles_only' => 0
        ]);

        $ruleId = (int)$Connection->lastInsertId();
        $this->additionalRuleIds[] = $ruleId;

        return $ruleId;
    }

    private function updateRule(array $data, object $Rule, string $table): void
    {
        $this->getConnection()->update($table, $data, ['id' => $this->ruleId]);
        $Rule->refresh();
    }

    private function getConnection(): Connection
    {
        return QUI::getDataBaseConnection();
    }
}
