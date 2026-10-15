<?php

namespace QUI\ERP\Shipping;

use Mcp\Server\Builder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\AI\MCP\Server;
use QUI\ERP\Shipping\Methods\Standard\ShippingType;
use QUI\ERP\Shipping\Rules\Factory as RuleFactory;
use QUI\ERP\Shipping\Rules\ShippingRule;
use QUI\ERP\Shipping\ShippingStatus\Handler as StatusHandler;
use QUI\ERP\Shipping\ShippingStatus\Status;
use QUI\ERP\Shipping\Types\Factory as ShippingFactory;
use QUI\ERP\Shipping\Types\ShippingEntry;
use QUI\Interfaces\Users\User;
use QUI\Permissions\Manager;
use QUI\Utils\Singleton;
use ReflectionProperty;

class McpProviderTest extends TestCase
{
    private mixed $previousRequestUser;
    private ?Manager $previousRights;
    private array $previousInstances;
    private ShippingFactory & MockObject $ShippingFactory;
    private RuleFactory & MockObject $RuleFactory;
    private Shipping & MockObject $Shipping;
    private StatusHandler & MockObject $StatusHandler;

    protected function setUp(): void
    {
        $this->previousRequestUser = (new ReflectionProperty(Server::class, 'RequestUser'))->getValue();
        $this->previousRights = QUI::$Rights;
        $Instances = new ReflectionProperty(Singleton::class, 'instances');
        $this->previousInstances = $Instances->getValue();
        $this->ShippingFactory = $this->createMock(ShippingFactory::class);
        $this->RuleFactory = $this->createMock(RuleFactory::class);
        $this->Shipping = $this->createMock(Shipping::class);
        $this->StatusHandler = $this->createMock(StatusHandler::class);
        $instances = $this->previousInstances;
        $instances[ShippingFactory::class] = $this->ShippingFactory;
        $instances[RuleFactory::class] = $this->RuleFactory;
        $instances[Shipping::class] = $this->Shipping;
        $instances[StatusHandler::class] = $this->StatusHandler;
        $Instances->setValue(null, $instances);
        $this->setPermissions([
            McpProvider::PERMISSION => true,
            'quiqqer.shipping.create' => true,
            'quiqqer.shipping.edit' => true,
            'quiqqer.shipping.delete' => true
        ]);
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Server::class, 'RequestUser'))->setValue(null, $this->previousRequestUser);
        (new ReflectionProperty(Singleton::class, 'instances'))->setValue(null, $this->previousInstances);
        QUI::$Rights = $this->previousRights;
    }

    public function testRegistrationAndRevokedPermissionAtEveryCallback(): void
    {
        $tools = $this->tools();
        self::assertCount(13, $tools);
        $this->setPermissions([]);
        self::assertSame([], $this->tools());
        $this->ShippingFactory->expects(self::never())->method('getChild');
        $this->ShippingFactory->expects(self::never())->method('createChild');
        $this->RuleFactory->expects(self::never())->method('createChild');

        foreach ($tools as $name => $tool) {
            self::assertFalse($tool['inputSchema']['additionalProperties']);
            self::assertNotEmpty($tool['description']);
            $arguments = match ($name) {
                'shipping_get', 'shipping_delete', 'shipping_rule_get', 'shipping_rule_delete' => [1],
                'shipping_update', 'shipping_rule_update' => [1, ['active' => true]],
                'shipping_create' => [ShippingType::class],
                default => []
            };
            self::assertTrue($tool['handler'](...$arguments)->isError, $name);
        }
    }

    public function testWriteToolsRequireTheirDomainPermission(): void
    {
        $this->setPermissions([McpProvider::PERMISSION => true]);
        $tools = $this->tools();
        $this->ShippingFactory->expects(self::never())->method('getChild');
        $this->ShippingFactory->expects(self::never())->method('createChild');
        $this->RuleFactory->expects(self::never())->method('getChild');
        $this->RuleFactory->expects(self::never())->method('createChild');

        foreach (['shipping', 'shipping_rule'] as $prefix) {
            self::assertTrue($tools[$prefix . '_update']['handler'](1, ['active' => true])->isError);
            self::assertTrue($tools[$prefix . '_delete']['handler'](1)->isError);
        }

        self::assertTrue($tools['shipping_create']['handler'](ShippingType::class)->isError);
        self::assertTrue($tools['shipping_rule_create']['handler']()->isError);
    }

    #[DataProvider('invalidIds')]
    public function testInvalidIdentifiersNeverReachFactories(mixed $id): void
    {
        $this->ShippingFactory->expects(self::never())->method('getChild');
        $this->RuleFactory->expects(self::never())->method('getChild');
        $tools = $this->tools();

        foreach (['shipping', 'shipping_rule'] as $prefix) {
            self::assertTrue($tools[$prefix . '_get']['handler']($id)->isError);
            self::assertTrue($tools[$prefix . '_delete']['handler']($id)->isError);
            self::assertTrue($tools[$prefix . '_update']['handler']($id, ['active' => true])->isError);
        }
    }

    public static function invalidIds(): array
    {
        return [[0], [-1], ['12oops'], ['12'], [12.5], [true], [null], [[]], [new \stdClass()]];
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidDataCannotCreateOrMutateEntries(mixed $data): void
    {
        $tools = $this->tools();
        $this->ShippingFactory->expects(self::never())->method('getChild');
        $this->ShippingFactory->expects(self::never())->method('createChild');
        $this->RuleFactory->expects(self::never())->method('getChild');
        $this->RuleFactory->expects(self::never())->method('createChild');

        self::assertTrue($tools['shipping_create']['handler'](ShippingType::class, $data)->isError);
        self::assertTrue($tools['shipping_update']['handler'](1, $data)->isError);
        self::assertTrue($tools['shipping_rule_create']['handler']($data)->isError);
        self::assertTrue($tools['shipping_rule_update']['handler'](1, $data)->isError);
    }

    public static function invalidPayloads(): array
    {
        return [
            'string instead of object' => ['{"active":true}'],
            'null' => [null],
            'list instead of object' => [[true]],
            'unknown field' => [['typo' => true]],
            'readonly identifier' => [['id' => 22]],
            'arbitrary class' => [['shipping_type' => \stdClass::class]],
            'string boolean' => [['active' => 'false']],
            'integer boolean' => [['active' => 1]],
            'fractional priority' => [['priority' => 2.5]],
            'overflow priority' => [['priority' => 2147483648]],
            'invalid translation type' => [['title' => ['en' => false]]],
            'unknown language' => [['title' => ['zz-invalid' => 'x']]],
            'empty translations' => [['title' => []]],
            'scalar translation' => [['title' => 'x']],
            'null byte' => [['title' => ['en' => "x\0y"]]],
            'oversized translation' => [['title' => ['en' => str_repeat('x', 10001)]]],
            'wrong restriction type' => [['areas' => '1,2']],
            'invalid restriction id' => [['areas' => [0]]],
            'duplicate restriction' => [['areas' => [1, 1]]],
            'object restriction' => [['articles' => ['id' => 1]]],
            'invalid principal prefix' => [['user_groups' => ['admin']]],
            'invalid principal id' => [['user_groups' => ['u0']]]
        ];
    }

    public function testListPaginationAndSortingAreValidatedBeforeQuerying(): void
    {
        $this->ShippingFactory->expects(self::never())->method('countChildren');
        $this->RuleFactory->expects(self::never())->method('countChildren');
        $tools = $this->tools();

        foreach (['shipping_list', 'shipping_rule_list'] as $name) {
            $list = $tools[$name]['handler'];
            self::assertTrue($list(0)->isError);
            self::assertTrue($list(101)->isError);
            self::assertTrue($list('20')->isError);
            self::assertTrue($list(20, -1)->isError);
            self::assertTrue($list(20, 0, 'false')->isError);
            self::assertTrue($list(20, 0, null, 'id; DROP TABLE shipping')->isError);
        }
    }

    public function testListUsesBoundedFactoryQueriesAndReturnsConfigurationOnly(): void
    {
        $Entry = $this->entry(false);
        $Entry->expects(self::never())->method('getPrice');
        $this->ShippingFactory->expects(self::once())->method('countChildren')
            ->with(['where' => ['active' => 0]])->willReturn(3);
        $this->ShippingFactory->expects(self::once())->method('getChildren')->with([
            'where' => ['active' => 0],
            'order' => 'id DESC',
            'limit' => '1,2'
        ])->willReturn([$Entry]);
        $result = $this->tools()['shipping_list']['handler'](2, 1, false, 'id DESC');

        self::assertSame(3, $result['count']);
        self::assertSame(2, $result['limit']);
        self::assertSame(1, $result['offset']);
        self::assertSame([12], array_column($result['entries'], 'id'));
        self::assertArrayNotHasKey('secret', $result['entries'][0]);
        self::assertArrayNotHasKey('price', $result['entries'][0]);
        self::assertSame([5], $result['entries'][0]['shipping_rules']);
    }

    public function testCreateResolvesRegisteredTypeAndConvertsTypedAttributes(): void
    {
        $Entry = $this->entry(false);
        $Type = $this->createMock(ShippingType::class);
        $Type->method('getType')->willReturn(ShippingType::class);
        $this->Shipping->expects(self::once())->method('getShippingType')->with(ShippingType::class)
            ->willReturn($Type);
        $this->ShippingFactory->expects(self::once())->method('createChild')->with([
            'priority' => 3,
            'shipping_rules' => '[]',
            'active' => 0,
            'shipping_type' => ShippingType::class
        ])->willReturn($Entry);
        $Entry->expects(self::once())->method('setTitle')->with(['en' => 'Standard']);
        $Entry->expects(self::never())->method('update');
        $result = $this->tools()['shipping_create']['handler'](ShippingType::class, [
            'title' => ['en' => 'Standard'],
            'priority' => 3,
            'shipping_rules' => []
        ]);

        self::assertSame(12, $result['id']);
    }

    public function testUnregisteredTypeDoesNotCreateAnEntry(): void
    {
        $this->Shipping->method('getShippingType')->willThrowException(new QUI\Exception('Unknown type'));
        $this->ShippingFactory->expects(self::never())->method('createChild');
        self::assertTrue($this->tools()['shipping_create']['handler'](\stdClass::class)->isError);
    }

    public function testUpdatePreservesOmittedFieldsAndChecksRulesBeforeMutation(): void
    {
        $Entry = $this->entry(false);
        $this->ShippingFactory->method('getChild')->with(12)->willReturn($Entry);
        $this->RuleFactory->expects(self::once())->method('getChild')->with(7)->willReturn($this->entry(true));
        $Entry->expects(self::once())->method('setAttributes')->with([
            'active' => 0,
            'shipping_rules' => '[7]'
        ]);
        $Entry->expects(self::never())->method('setTitle');
        $Entry->expects(self::never())->method('removeIcon');
        $Entry->expects(self::once())->method('update');

        self::assertIsArray($this->tools()['shipping_update']['handler'](12, [
            'active' => false,
            'shipping_rules' => [7]
        ]));
    }

    public function testMissingRulePreventsAllMutations(): void
    {
        $Entry = $this->entry(false);
        $this->ShippingFactory->method('getChild')->willReturn($Entry);
        $this->RuleFactory->method('getChild')->willThrowException(new QUI\Exception('Missing rule'));
        $Entry->expects(self::never())->method('setAttributes');
        $Entry->expects(self::never())->method('setTitle');
        $Entry->expects(self::never())->method('update');
        self::assertTrue($this->tools()['shipping_update']['handler'](12, [
            'title' => ['en' => 'Changed'],
            'shipping_rules' => [999]
        ])->isError);
    }

    #[DataProvider('invalidRules')]
    public function testInvalidRuleSettingsCannotBeCreated(array $data): void
    {
        $this->RuleFactory->expects(self::never())->method('createChild');
        self::assertTrue($this->tools()['shipping_rule_create']['handler']($data)->isError);
    }

    public static function invalidRules(): array
    {
        return [
            [['discount' => INF]],
            [['discount' => NAN]],
            [['discount' => -1]],
            [['discount_type' => 3]],
            [['discount_type' => 'ABS']],
            [['date_from' => 'tomorrow']],
            [['date_from' => '2026-02-30']],
            [['date_from' => '2026-11-01', 'date_until' => '2026-10-01']],
            [['purchase_quantity_from' => 10, 'purchase_quantity_until' => 2]],
            [['purchase_value_from' => 10, 'purchase_value_until' => 2]],
            [['unit_terms' => [['id' => 1, 'unit' => 'kg', 'term' => 'nonsense', 'value' => 2]]]],
            [['unit_terms' => [['id' => 1, 'unit' => 'kg', 'term' => 'gt']]]]
        ];
    }

    public function testPartialRuleUpdateChecksExistingRangeBeforeMutation(): void
    {
        $Rule = $this->entry(true, ['purchase_value_until' => 10]);
        $this->RuleFactory->method('getChild')->willReturn($Rule);
        $Rule->expects(self::never())->method('setAttributes');
        $Rule->expects(self::never())->method('update');

        self::assertTrue($this->tools()['shipping_rule_update']['handler'](12, ['purchase_value_from' => 20])->isError);
    }

    public function testRuleCreateUsesDefaultsAndAllowsRemovingDateBounds(): void
    {
        $Rule = $this->entry(true);
        $this->RuleFactory->expects(self::once())->method('createChild')->with([
            'date_from' => '',
            'active' => 0,
            'discount_type' => 0
        ])->willReturn($Rule);

        self::assertSame(12, $this->tools()['shipping_rule_create']['handler'](['date_from' => ''])['id']);
    }

    public function testRuleDeletionRejectsAttachedRules(): void
    {
        $Rule = $this->entry(true);
        $Entry = $this->entry(false);
        $Entry->method('getAttribute')->with('shipping_rules')->willReturn('[12]');
        $this->RuleFactory->method('getChild')->willReturn($Rule);
        $this->ShippingFactory->method('getChildren')->willReturn([$Entry]);
        $Rule->expects(self::never())->method('delete');

        self::assertTrue($this->tools()['shipping_rule_delete']['handler'](12)->isError);
    }

    public function testDeleteCallsThePublicDomainApi(): void
    {
        $Entry = $this->entry(false);
        $Rule = $this->entry(true);
        $Entry->expects(self::once())->method('delete');
        $Rule->expects(self::once())->method('delete');
        $this->ShippingFactory->method('getChild')->willReturn($Entry);
        $this->ShippingFactory->method('getChildren')->willReturn([]);
        $this->RuleFactory->method('getChild')->willReturn($Rule);
        $tools = $this->tools();

        self::assertSame(['id' => 12, 'deleted' => true], $tools['shipping_delete']['handler'](12));
        self::assertSame(['id' => 12, 'deleted' => true], $tools['shipping_rule_delete']['handler'](12));
    }

    public function testTypesAndStatusesAreStructuredAndReadOnly(): void
    {
        $Type = $this->createMock(ShippingType::class);
        $Type->method('toArray')->willReturn(['title' => 'Standard', 'type' => ShippingType::class]);
        $this->Shipping->method('getShippingTypes')->willReturn(['standard' => $Type]);
        $Status = $this->createMock(Status::class);
        $Status->method('toArray')->willReturn([
            'id' => 1,
            'title' => ['en' => 'Shipped'],
            'color' => '#000000',
            'notification' => true,
            'statusChangeText' => []
        ]);
        $this->StatusHandler->method('getShippingStatusList')->willReturn([$Status]);
        $tools = $this->tools();

        self::assertSame(ShippingType::class, $tools['shipping_types']['handler']()['types'][0]['type']);
        self::assertTrue($tools['shipping_status_list']['handler']()['statuses'][0]['notification']);
    }

    public function testDomainExceptionsBecomeMcpErrors(): void
    {
        $this->ShippingFactory->method('getChild')->willThrowException(new QUI\Exception('Not found'));
        self::assertTrue($this->tools()['shipping_get']['handler'](999)->isError);
    }

    private function entry(bool $rule, array $attributes = []): (ShippingEntry & MockObject) | (ShippingRule & MockObject)
    {
        $Entry = $this->createMock($rule ? ShippingRule::class : ShippingEntry::class);
        $Entry->method('getId')->willReturn(12);
        $Entry->method('getAttributes')->willReturn(array_replace([
            'id' => 12,
            'active' => 1,
            'priority' => 2,
            'secret' => 'never expose provider data',
            'shipping_type' => ShippingType::class,
            'shipping_rules' => '[5]'
        ], $attributes));
        $Entry->method('isActive')->willReturn(true);

        return $Entry;
    }

    private function tools(): array
    {
        $Builder = new Builder();
        (new McpProvider())->register($Builder);
        $tools = (new ReflectionProperty(Builder::class, 'tools'))->getValue($Builder);

        return array_column($tools, null, 'name');
    }

    private function setPermissions(array $permissions): void
    {
        $User = $this->createMock(User::class);
        $User->method('getUUID')->willReturn('shipping-mcp-test-user');
        $User->method('getGroups')->willReturn([]);
        $Rights = $this->createMock(Manager::class);
        $Rights->method('getPermissions')->willReturn($permissions);
        QUI::$Rights = $Rights;
        (new ReflectionProperty(Server::class, 'RequestUser'))->setValue(null, $User);
    }
}
