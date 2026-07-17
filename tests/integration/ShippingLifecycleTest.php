<?php

namespace QUITests\ERP\Shipping\Integration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Order\Factory as OrderFactory;
use QUI\ERP\Order\Handler as OrderHandler;
use QUI\ERP\Shipping\Rules\Factory as RuleFactory;
use QUI\ERP\Shipping\Shipping;
use QUI\ERP\Shipping\Types\Factory as ShippingFactory;
use QUI\ERP\Shipping\Types\ShippingEntry;
use ReflectionProperty;
use Throwable;
use QUITests\ERP\Shipping\Stubs\AlwaysAvailableShippingType;

class ShippingLifecycleTest extends TestCase
{
    private ?int $shippingId = null;
    private ?int $ruleId = null;
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
        }

        if ($this->ruleId !== null) {
            $Connection->delete(RuleFactory::getInstance()->getDataBaseTableName(), ['id' => $this->ruleId]);
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
        self::assertSame(RuleFactory::DISCOUNT_TYPE_ABS, $Rule->getDiscountType());
        self::assertFalse($Rule->noRulesAfter());
        self::assertSame(['weight' => ['from' => 0, 'until' => 20]], $Rule->getUnitTerms());
        self::assertArrayHasKey('discount', $Rule->toArray());
        self::assertSame(1, $RuleFactory->countChildren(['where' => ['id' => $this->ruleId]]));

        $Factory = ShippingFactory::getInstance();
        $Entry = $Factory->getChild($this->shippingId);

        self::assertInstanceOf(ShippingEntry::class, $Entry);
        self::assertSame($this->shippingId, $Entry->getId());
        self::assertTrue($Entry->isActive());
        self::assertInstanceOf(AlwaysAvailableShippingType::class, $Entry->getShippingType());
        self::assertCount(1, $Entry->getShippingRules());
        self::assertSame(5.5, $Entry->getPrice());
        self::assertTrue($Entry->isValid());
        self::assertJson($Entry->toJSON());
        self::assertSame(AlwaysAvailableShippingType::class, $Entry->toArray()['shipping_type']);

        $Entry->deactivate();
        self::assertFalse($Entry->isActive());
        self::assertFalse($Entry->isValid());
        $Entry->activate();
        self::assertTrue($Entry->isActive());

        $Entry->addShippingRuleId($this->ruleId);
        self::assertCount(1, json_decode((string)$Entry->getAttribute('shipping_rules'), true));
        $Entry->removeIcon();
        self::assertFalse($Entry->getAttribute('icon'));

        $Shipping = Shipping::getInstance();
        self::assertSame($this->shippingId, $Shipping->getShippingEntry($this->shippingId)->getId());
        self::assertContains(
            $this->shippingId,
            array_map(static fn (ShippingEntry $Item): int => $Item->getId(), $Shipping->getShippingList())
        );
        self::assertNotSame('', $Shipping->getHost());
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

    private function getConnection(): Connection
    {
        return QUI::getDataBaseConnection();
    }
}
