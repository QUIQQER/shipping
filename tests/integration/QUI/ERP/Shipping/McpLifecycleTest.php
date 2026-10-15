<?php

namespace QUI\ERP\Shipping;

use Mcp\Server\Builder;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\AI\MCP\Server;
use QUI\ERP\Shipping\Methods\Standard\ShippingType;
use QUI\ERP\Shipping\Rules\Factory as RuleFactory;
use QUI\ERP\Shipping\Types\Factory as ShippingFactory;
use ReflectionProperty;
use Throwable;

class McpLifecycleTest extends TestCase
{
    private mixed $previousSessionUser = null;
    private mixed $previousRequestUser = null;
    private array $shippingIds = [];
    private array $ruleIds = [];
    private bool $available = false;

    protected function setUp(): void
    {
        try {
            QUI::getDataBaseConnection()->executeQuery('SELECT 1')->free();
        } catch (Throwable) {
            self::markTestSkipped('QUIQQER database is unavailable.');
        }

        $this->available = true;
        $Users = QUI::getUsers();
        $Session = new ReflectionProperty($Users, 'Session');
        $this->previousSessionUser = $Session->getValue($Users);
        $this->previousRequestUser = (new ReflectionProperty(Server::class, 'RequestUser'))->getValue();
        $Session->setValue($Users, $Users->getSystemUser());
        (new ReflectionProperty(Server::class, 'RequestUser'))->setValue(null, $Users->getSystemUser());
    }

    protected function tearDown(): void
    {
        if (!$this->available) {
            return;
        }

        try {
            foreach ($this->shippingIds as $id) {
                QUI::getDataBaseConnection()->delete(ShippingFactory::getInstance()->getDataBaseTableName(), ['id' => $id]);

                foreach (['title', 'workingTitle', 'description'] as $field) {
                    QUI\Translator::delete('quiqqer/shipping', 'shipping.' . $id . '.' . $field);
                }
            }

            foreach ($this->ruleIds as $id) {
                QUI::getDataBaseConnection()->delete(RuleFactory::getInstance()->getDataBaseTableName(), ['id' => $id]);

                foreach (['title', 'workingTitle'] as $field) {
                    QUI\Translator::delete('quiqqer/shipping', 'shipping.' . $id . '.rule.' . $field);
                }
            }
        } finally {
            (new ReflectionProperty(Server::class, 'RequestUser'))->setValue(null, $this->previousRequestUser);
            (new ReflectionProperty(QUI::getUsers(), 'Session'))->setValue(QUI::getUsers(), $this->previousSessionUser);
        }
    }

    public function testMcpConfigurationLifecyclePersistsTypedData(): void
    {
        $tools = $this->tools();
        $language = QUI::getLocale()->getCurrent();
        $rule = $tools['shipping_rule_create']['handler']([
            'title' => [$language => 'PHPUnit MCP shipping rule'],
            'discount' => 4.5,
            'discount_type' => 0,
            'date_from' => '2026-01-01',
            'date_until' => '2026-12-31'
        ]);
        self::assertIsArray($rule);
        $this->ruleIds[] = $rule['id'];
        self::assertFalse($rule['active']);
        self::assertSame(4.5, $rule['discount']);
        self::assertSame('PHPUnit MCP shipping rule', $rule['title'][$language]);
        self::assertSame(4.5, RuleFactory::getInstance()->getChild($rule['id'])->getDiscount());

        $shipping = $tools['shipping_create']['handler'](ShippingType::class, [
            'title' => [$language => 'PHPUnit MCP shipping method'],
            'priority' => 50,
            'shipping_rules' => [$rule['id']]
        ]);
        self::assertIsArray($shipping);
        $this->shippingIds[] = $shipping['id'];
        self::assertFalse($shipping['active']);
        self::assertSame('PHPUnit MCP shipping method', $shipping['title'][$language]);
        self::assertSame([$rule['id']], $shipping['shipping_rules']);
        self::assertSame($shipping['id'], $tools['shipping_get']['handler']($shipping['id'])['id']);
        self::assertTrue($tools['shipping_rule_delete']['handler']($rule['id'])->isError);

        $updated = $tools['shipping_update']['handler']($shipping['id'], ['active' => true, 'shipping_rules' => []]);
        self::assertIsArray($updated);
        self::assertTrue(ShippingFactory::getInstance()->getChild($shipping['id'])->isActive());
        self::assertSame([], $updated['shipping_rules']);
        self::assertSame($shipping['title'], $updated['title']);
        $list = $tools['shipping_list']['handler'](100, 0, true, 'id DESC');
        self::assertContains($shipping['id'], array_column($list['entries'], 'id'));

        $changedRule = $tools['shipping_rule_update']['handler']($rule['id'], [
            'active' => true,
            'discount' => 6.25,
            'date_until' => ''
        ]);
        self::assertSame(6.25, $changedRule['discount']);
        self::assertSame('', RuleFactory::getInstance()->getChild($rule['id'])->getAttribute('date_until'));
        self::assertTrue($tools['shipping_rule_delete']['handler']($rule['id'])['deleted']);
        self::assertTrue($tools['shipping_delete']['handler']($shipping['id'])['deleted']);
        self::assertTrue($tools['shipping_get']['handler']($shipping['id'])->isError);
    }

    private function tools(): array
    {
        $Builder = new Builder();
        (new McpProvider())->register($Builder);

        return array_column((new ReflectionProperty(Builder::class, 'tools'))->getValue($Builder), null, 'name');
    }
}
