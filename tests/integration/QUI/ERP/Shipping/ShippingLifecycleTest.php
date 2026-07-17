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
use QUI\ERP\Products\Handler\Products;
use QUI\ERP\Products\Product\Types\AbstractType;
use QUI\ERP\Products\Product\Types\DigitalProduct;
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
        $Entry->setIcon('/image.php?project=phpunit&id=999999');
        self::assertSame('/image.php?project=phpunit&id=999999', $Entry->getAttribute('icon'));
        self::assertSame('/phpunit-shipping.svg', $Entry->getIcon());

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

    public function testRuleSaveNormalizesEditableAttributes(): void
    {
        $Rule = RuleFactory::getInstance()->getChild($this->ruleId);
        $Rule->setAttributes(array_merge($Rule->getAttributes(), [
            'title' => [],
            'workingTitle' => [],
            'discount' => '12.5',
            'discount_type' => 'PERCENTAGE_ORDER',
            'articles_only' => true,
            'no_rule_after' => true,
            'unit_terms' => [['id' => 1, 'value' => 2]],
            'purchase_quantity_from' => '',
            'purchase_quantity_until' => '',
            'purchase_value_from' => '',
            'purchase_value_until' => ''
        ]));

        $Rule->update();
        $Rule->refresh();

        self::assertSame(12.5, $Rule->getDiscount());
        self::assertSame(RuleFactory::DISCOUNT_TYPE_PC_ORDER, $Rule->getDiscountType());
        self::assertTrue($Rule->noRulesAfter());
        self::assertSame([['id' => 1, 'value' => 2]], $Rule->getUnitTerms());
        self::assertSame(1, (int)$Rule->getAttribute('articles_only'));
        self::assertNull($Rule->getAttribute('purchase_value_from'));
        self::assertNull($Rule->getAttribute('purchase_value_until'));
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
            ['user_groups' => 'u' . $SystemUser->getId()],
            ['id' => $Standard->getId()]
        );
        $Standard->refresh();
        self::assertTrue($StandardType->canUsedBy($SystemUser, $Standard, $Order));

        $this->getConnection()->update(
            ShippingFactory::getInstance()->getDataBaseTableName(),
            ['user_groups' => 'u-user-that-does-not-exist'],
            ['id' => $Standard->getId()]
        );
        $Standard->refresh();
        self::assertFalse($StandardType->canUsedBy($SystemUser, $Standard, $Order));

        $this->getConnection()->update(
            ShippingFactory::getInstance()->getDataBaseTableName(),
            ['active' => 0],
            ['id' => $Standard->getId()]
        );
        $Standard->refresh();
        self::assertFalse($StandardType->canUsedBy($SystemUser, $Standard, $Order));

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

        $this->updateRule([
            'unit_terms' => json_encode([
                'invalid-term',
                ['id' => 9999, 'unit' => 'piece'],
                ['id' => 9999, 'unit' => 'piece', 'value' => 1]
            ], JSON_THROW_ON_ERROR)
        ], $Rule, $table);
        self::assertTrue($Rule->canUsedIn($Order));

        $this->updateRule(['user_groups' => 'u-user-that-does-not-exist'], $Rule, $table);
        self::assertFalse($Rule->canUsedIn($Order));

        $this->updateRule(['active' => 0, 'user_groups' => null], $Rule, $table);
        self::assertFalse($Rule->canUsedIn($Order));
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

        $validShipping = Shipping::getInstance()->getValidShippingEntries($Order);
        self::assertNotEmpty($validShipping);
        self::assertCount(
            count($validShipping),
            Shipping::getInstance()->getValidShippingEntriesByOrder($Order)
        );

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

    public function testOrderUpdateEventRemovesAndReplacesStaleShippingFactors(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Order = OrderFactory::getInstance()->create($SystemUser, false, null, uniqid('shipping-factor-', true));
        $this->orderHash = $Order->getUUID();
        $Order->setCustomer($SystemUser);
        $Order->setDeliveryAddress([
            'id' => 91010,
            'firstname' => 'PHPUnit',
            'lastname' => 'Shipping',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE'
        ]);
        $Order->getArticles()->addArticle(new Article([
            'id' => 65432109,
            'articleNo' => 'SHIPPING-FACTOR',
            'title' => 'Shipping factor article',
            'unitPrice' => 20,
            'quantity' => 1,
            'vat' => 19
        ]));
        $Order->getArticles()->calc();

        $Entry = ShippingFactory::getInstance()->getChild($this->shippingId);
        $Entry->setErpEntity($Order);
        $Factors = $Order->getArticles()->getPriceFactors();
        $Factors->addFactor($Entry->toPriceFactor(null, $Order)->toErpPriceFactor());
        self::assertSame(1, $Factors->count());

        $data = [];
        EventHandler::onQuiqqerOrderUpdateBegin($Order, $data);
        self::assertSame(0, $Factors->count());

        $Factors->addFactor($Entry->toPriceFactor(null, $Order)->toErpPriceFactor());
        $Replacement = $this->insertAdditionalShippingEntry(AlwaysAvailableShippingType::class);
        $Replacement->setErpEntity($Order);
        $Order->setShipping($Replacement);
        EventHandler::onQuiqqerOrderUpdateBegin($Order, $data);

        self::assertSame(
            'shipping-pricefactor-' . $Replacement->getId(),
            $Factors->getFactor(0)->getIdentifier()
        );
        self::assertArrayHasKey('articles', $data);
    }

    public function testCustomerChangeRevalidatesAndReplacesShippingFactor(): void
    {
        $Config = QUI::getPackage('quiqqer/shipping')->getConfig();
        $previous = $Config->getValue('shipping', 'considerCustomerCountry');
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Order = OrderFactory::getInstance()->create($SystemUser, false, null, uniqid('shipping-customer-', true));
        $this->orderHash = $Order->getUUID();
        $Order->setCustomer($SystemUser);
        $Order->setDeliveryAddress([
            'id' => 91011,
            'firstname' => 'PHPUnit',
            'lastname' => 'Shipping',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE'
        ]);
        $Order->getArticles()->addArticle(new Article([
            'id' => 54321098,
            'articleNo' => 'SHIPPING-CUSTOMER',
            'title' => 'Customer change article',
            'unitPrice' => 25,
            'quantity' => 1,
            'vat' => 19
        ]));
        $Order->getArticles()->calc();
        $Entry = ShippingFactory::getInstance()->getChild($this->shippingId);
        $Entry->setErpEntity($Order);
        $Order->getArticles()->getPriceFactors()->addFactor(
            $Entry->toPriceFactor(null, $Order)->toErpPriceFactor()
        );

        try {
            $Config->setValue('shipping', 'considerCustomerCountry', 1);
            $Config->save();

            EventHandler::onQuiqqerCustomerChange($Order);

            self::assertSame($this->shippingId, $Order->getShipping()?->getId());
            self::assertSame($this->shippingId, $Order->getAttribute('__SHIPPING__')?->getId());
            self::assertSame(
                'shipping-pricefactor-' . $this->shippingId,
                $Order->getArticles()->getPriceFactors()->getFactor(0)->getIdentifier()
            );
        } finally {
            if ($previous === null) {
                $Config->del('shipping', 'considerCustomerCountry');
            } else {
                $Config->setValue('shipping', 'considerCustomerCountry', $previous);
            }

            $Config->save();
        }
    }

    public function testProductTypesAndUnitTermsUseRuntimeProductData(): void
    {
        $productId = 42424242;
        $ProductsList = new ReflectionProperty(Products::class, 'list');
        $previousProducts = $ProductsList->getValue();
        $Config = QUI::getPackage('quiqqer/shipping')->getConfig();
        $previousRuleFields = $Config->getValue('shipping', 'ruleFields');
        $Field = $this->createMock(QUI\ERP\Products\Field\Field::class);
        $Field->method('getValue')->willReturn(2);
        $Field->method('getTitle')->willReturn('PHPUnit unit');
        $Category = $this->createMock(QUI\ERP\Products\Category\Category::class);
        $Category->method('getId')->willReturn(1);
        $PhysicalProduct = $this->createMock(AbstractType::class);
        $PhysicalProduct->method('getCategories')->willReturn([$Category]);
        $PhysicalProduct->method('getField')->with(999)->willReturn($Field);
        $DigitalProduct = $this->createMock(DigitalProduct::class);
        $DigitalProduct->method('getCategories')->willReturn([$Category]);
        $DigitalProduct->method('getField')->with(999)->willReturn($Field);

        $SystemUser = QUI::getUsers()->getSystemUser();
        $Order = OrderFactory::getInstance()->create($SystemUser, false, null, uniqid('shipping-product-', true));
        $this->orderHash = $Order->getUUID();
        $Order->setCustomer($SystemUser);
        $Order->setDeliveryAddress([
            'id' => 91012,
            'firstname' => 'PHPUnit',
            'lastname' => 'Shipping',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE'
        ]);
        $Order->getArticles()->addArticle(new Article([
            'id' => $productId,
            'articleNo' => 'SHIPPING-PRODUCT',
            'title' => 'Runtime product',
            'unitPrice' => 20,
            'quantity' => 2,
            'vat' => 19
        ]));
        $Order->getArticles()->calc();

        $Standard = $this->insertAdditionalShippingEntry(StandardShippingType::class);
        $Digital = $this->insertAdditionalShippingEntry(DigitalShippingType::class);
        $StandardType = new StandardShippingType();
        $DigitalType = new DigitalShippingType();

        try {
            $Config->setValue('shipping', 'ruleFields', '999');
            $Config->save();

            $ProductsList->setValue(null, [$productId => $PhysicalProduct]);
            self::assertTrue($StandardType->canUsedInOrder($Order, $Standard));
            self::assertFalse($DigitalType->canUsedInOrder($Order, $Digital));

            $this->getConnection()->update(
                ShippingFactory::getInstance()->getDataBaseTableName(),
                ['categories' => '1'],
                ['id' => $Standard->getId()]
            );
            $Standard->refresh();
            self::assertTrue($StandardType->canUsedInOrder($Order, $Standard));
            $this->getConnection()->update(
                ShippingFactory::getInstance()->getDataBaseTableName(),
                ['categories' => '2'],
                ['id' => $Standard->getId()]
            );
            $Standard->refresh();
            self::assertFalse($StandardType->canUsedInOrder($Order, $Standard));

            $ProductsList->setValue(null, [$productId => $DigitalProduct]);
            self::assertFalse($StandardType->canUsedInOrder($Order, $Standard));
            self::assertTrue($DigitalType->canUsedInOrder($Order, $Digital));

            $previousShippingRequest = $_REQUEST['shipping'] ?? null;

            try {
                $_REQUEST['shipping'] = $Standard->getId();
                (new ShippingStep(['Order' => $Order]))->save();
                self::assertNull($Order->getShipping());
            } finally {
                if ($previousShippingRequest === null) {
                    unset($_REQUEST['shipping']);
                } else {
                    $_REQUEST['shipping'] = $previousShippingRequest;
                }
            }

            $this->getConnection()->update(
                ShippingFactory::getInstance()->getDataBaseTableName(),
                ['categories' => '1'],
                ['id' => $Digital->getId()]
            );
            $Digital->refresh();
            self::assertTrue($DigitalType->canUsedInOrder($Order, $Digital));
            $this->getConnection()->update(
                ShippingFactory::getInstance()->getDataBaseTableName(),
                ['categories' => '2'],
                ['id' => $Digital->getId()]
            );
            $Digital->refresh();
            self::assertFalse($DigitalType->canUsedInOrder($Order, $Digital));

            $Rule = RuleFactory::getInstance()->getChild($this->ruleId);
            $this->updateRule([
                'categories' => '1',
                'unit_terms' => json_encode([[
                    'id' => 999,
                    'unit' => '',
                    'value' => 3,
                    'term' => 'gt'
                ]], JSON_THROW_ON_ERROR)
            ], $Rule, RuleFactory::getInstance()->getDataBaseTableName());
            self::assertTrue($Rule->canUsedIn($Order));

            $this->updateRule([
                'unit_terms' => json_encode([[
                    'id' => 999,
                    'unit' => '',
                    'value' => 5,
                    'term' => 'gt'
                ]], JSON_THROW_ON_ERROR)
            ], $Rule, RuleFactory::getInstance()->getDataBaseTableName());
            self::assertFalse($Rule->canUsedIn($Order));

            $WeightField = $this->createMock(QUI\ERP\Products\Field\Field::class);
            $WeightField->method('getId')->willReturn(22);
            $WeightField->method('getValue')->willReturn(['quantity' => 2, 'id' => 'kg']);
            $WeightField->method('getTitle')->willReturn('Weight');
            $WeightedProduct = $this->createMock(AbstractType::class);
            $WeightedProduct->method('getCategories')->willReturn([$Category]);
            $WeightedProduct->method('getField')->with(22)->willReturn($WeightField);
            $ProductsList->setValue(null, [$productId => $WeightedProduct]);
            $Config->setValue('shipping', 'ruleFields', '22');
            $Config->save();

            $this->updateRule([
                'unit_terms' => json_encode([[
                    'id' => 22,
                    'unit' => 'kg',
                    'value' => 3,
                    'term' => 'gt',
                    'value2' => 5,
                    'term2' => 'lt'
                ]], JSON_THROW_ON_ERROR)
            ], $Rule, RuleFactory::getInstance()->getDataBaseTableName());
            self::assertTrue($Rule->canUsedIn($Order));

            $this->updateRule([
                'unit_terms' => json_encode([[
                    'id' => 22,
                    'unit' => 'kg',
                    'value' => 5,
                    'term' => 'gt'
                ]], JSON_THROW_ON_ERROR)
            ], $Rule, RuleFactory::getInstance()->getDataBaseTableName());
            self::assertFalse($Rule->canUsedIn($Order));

            $this->updateRule([
                'unit_terms' => json_encode([[
                    'id' => 22,
                    'unit' => 'kg',
                    'value' => 3,
                    'term' => 'gt',
                    'value2' => 3,
                    'term2' => 'lt'
                ]], JSON_THROW_ON_ERROR)
            ], $Rule, RuleFactory::getInstance()->getDataBaseTableName());
            self::assertFalse($Rule->canUsedIn($Order));
        } finally {
            $ProductsList->setValue(null, $previousProducts);

            if ($previousRuleFields === null) {
                $Config->del('shipping', 'ruleFields');
            } else {
                $Config->setValue('shipping', 'ruleFields', $previousRuleFields);
            }

            $Config->save();
        }
    }

    public function testShippingEntryHandlesEmptyRulesInvalidTypesAndInactiveState(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Order = OrderFactory::getInstance()->create($SystemUser, false, null, uniqid('shipping-entry-', true));
        $this->orderHash = $Order->getUUID();
        $Order->setCustomer($SystemUser);
        $Order->setDeliveryAddress([
            'id' => 91013,
            'firstname' => 'PHPUnit',
            'lastname' => 'Shipping',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE'
        ]);
        $table = ShippingFactory::getInstance()->getDataBaseTableName();
        $Entry = $this->insertAdditionalShippingEntry(AlwaysAvailableShippingType::class);
        $Entry->setErpEntity($Order);

        self::assertSame([], $Entry->getShippingRules());
        self::assertTrue($Entry->isValid());
        self::assertSame(0, $Entry->getPrice());
        self::assertSame('', $Entry->getPriceDisplay());

        $this->getConnection()->update($table, ['shipping_rules' => '[]'], ['id' => $Entry->getId()]);
        $Entry->refresh();
        self::assertSame([], $Entry->getShippingRules());
        self::assertFalse($Entry->isValid());

        $this->getConnection()->update(
            $table,
            ['shipping_rules' => null, 'shipping_type' => 'Missing\\Shipping\\Type'],
            ['id' => $Entry->getId()]
        );
        $Entry->refresh();

        try {
            $Entry->getShippingType();
            self::fail('Missing shipping type must throw.');
        } catch (ShippingException) {
            self::assertFalse($Entry->canUsedBy($SystemUser, $Order));
            self::assertFalse($Entry->canUsedInErpEntity($Order));
        }

        $this->getConnection()->update($table, ['shipping_type' => \stdClass::class], ['id' => $Entry->getId()]);
        $Entry->refresh();
        $this->expectException(ShippingException::class);
        $Entry->getShippingType();
    }

    public function testInactiveShippingEntryIsRejectedBeforeTypeChecks(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Order = OrderFactory::getInstance()->create($SystemUser, false, null, uniqid('shipping-inactive-', true));
        $this->orderHash = $Order->getUUID();
        $Order->setCustomer($SystemUser);
        $Entry = $this->insertAdditionalShippingEntry(AlwaysAvailableShippingType::class);
        $this->getConnection()->update(
            ShippingFactory::getInstance()->getDataBaseTableName(),
            ['active' => 0],
            ['id' => $Entry->getId()]
        );
        $Entry->refresh();

        self::assertFalse($Entry->isValid());
        self::assertFalse($Entry->canUsedBy($SystemUser, $Order));
        self::assertFalse($Entry->canUsedInErpEntity($Order));
    }

    public function testShippingRuleDebuggingRecordsValidTerminalAndInactiveRules(): void
    {
        $Config = QUI::getPackage('quiqqer/shipping')->getConfig();
        $previousDebug = $Config->getValue('shipping', 'debug');
        $Shipping = Shipping::getInstance();
        $debuggingProperty = new ReflectionProperty($Shipping, 'debugging');
        $previousCachedDebug = $debuggingProperty->getValue($Shipping);
        $Rule = RuleFactory::getInstance()->getChild($this->ruleId);
        $Entry = ShippingFactory::getInstance()->getChild($this->shippingId);
        $table = RuleFactory::getInstance()->getDataBaseTableName();

        try {
            $Config->setValue('shipping', 'debug', 1);
            $Config->save();
            $debuggingProperty->setValue($Shipping, null);

            $this->updateRule(['active' => 1, 'no_rule_after' => 1], $Rule, $table);
            Debug::clearLogStock();
            Debug::enable();
            self::assertCount(1, $Entry->getShippingRules());
            self::assertStringContainsString('no rules after', implode("\n", Debug::getLogStack()));

            $this->updateRule(['active' => 0, 'no_rule_after' => 0], $Rule, $table);
            Debug::clearLogStock();
            self::assertSame([], $Entry->getShippingRules());
            self::assertStringContainsString('is not valid', implode("\n", Debug::getLogStack()));
        } finally {
            Debug::disable();
            Debug::clearLogStock();
            $this->updateRule(['active' => 1, 'no_rule_after' => 0], $Rule, $table);

            if ($previousDebug === null) {
                $Config->del('shipping', 'debug');
            } else {
                $Config->setValue('shipping', 'debug', $previousDebug);
            }

            $Config->save();
            $debuggingProperty->setValue($Shipping, $previousCachedDebug);
        }
    }

    public function testPaymentEventAllowsConfiguredPaymentAndRejectsOthers(): void
    {
        $payments = array_values(array_filter(
            QUI\ERP\Accounting\Payments\Payments::getInstance()->getPayments(),
            static fn ($Payment): bool => $Payment instanceof Payment
        ));

        if ($payments === []) {
            self::markTestSkipped('No persisted payment is available for the shipping payment flow.');
        }

        $AllowedPayment = $payments[0];
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Order = OrderFactory::getInstance()->create($SystemUser, false, null, uniqid('shipping-payment-', true));
        $this->orderHash = $Order->getUUID();
        $Order->setCustomer($SystemUser);
        $Order->getArticles()->addArticle(new Article([
            'id' => 43210987,
            'articleNo' => 'SHIPPING-PAYMENT',
            'title' => 'Shipping payment article',
            'unitPrice' => 10,
            'quantity' => 1,
            'vat' => 19
        ]));
        $Order->getArticles()->calc();
        $Entry = ShippingFactory::getInstance()->getChild($this->shippingId);
        $this->getConnection()->update(
            ShippingFactory::getInstance()->getDataBaseTableName(),
            ['payments' => (string)$AllowedPayment->getId()],
            ['id' => $Entry->getId()]
        );
        $Entry->refresh();
        $Order->setShipping($Entry);

        EventHandler::onQuiqqerPaymentCanUsedInOrder($AllowedPayment, $Order);
        self::assertTrue(true);

        $DeniedPayment = $this->createMock(Payment::class);
        $DeniedPayment->method('getId')->willReturn(PHP_INT_MAX);

        $this->expectException(
            QUI\ERP\Accounting\Payments\Exceptions\PaymentCanNotBeUsed::class
        );
        EventHandler::onQuiqqerPaymentCanUsedInOrder($DeniedPayment, $Order);
    }

    public function testFractionalShippingPriceUsesRoundedApproximateDisplay(): void
    {
        $fractionalRuleId = $this->insertAdditionalRule(
            0.123456,
            RuleFactory::DISCOUNT_TYPE_ABS,
            5
        );
        $Entry = $this->insertAdditionalShippingEntry(AlwaysAvailableShippingType::class);
        $this->getConnection()->update(
            ShippingFactory::getInstance()->getDataBaseTableName(),
            ['shipping_rules' => json_encode([$fractionalRuleId], JSON_THROW_ON_ERROR)],
            ['id' => $Entry->getId()]
        );
        $Entry->refresh();

        $SystemUser = QUI::getUsers()->getSystemUser();
        $Order = OrderFactory::getInstance()->create($SystemUser, false, null, uniqid('shipping-round-', true));
        $this->orderHash = $Order->getUUID();
        $Order->setCustomer($SystemUser);
        $Order->setDeliveryAddress([
            'id' => 91014,
            'firstname' => 'PHPUnit',
            'lastname' => 'Shipping',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE'
        ]);
        $Order->getArticles()->addArticle(new Article([
            'id' => 32109876,
            'articleNo' => 'SHIPPING-ROUND',
            'title' => 'Fractional shipping article',
            'unitPrice' => 10,
            'quantity' => 1,
            'vat' => 19
        ]));
        $Order->getArticles()->calc();
        $Entry->setErpEntity($Order);

        self::assertSame(0.123456, $Entry->getPrice());
        self::assertStringContainsString('~', $Entry->getPriceDisplay());
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
