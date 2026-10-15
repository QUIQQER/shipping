<?php

namespace QUI\ERP\Shipping;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI;
use QUI\AI\MCP\ProviderInterface;
use QUI\AI\MCP\Server;
use QUI\AI\MCP\ToolHelper;
use QUI\CRUD\Factory;
use QUI\ERP\Shipping\Mcp\Input;
use QUI\ERP\Shipping\Rules\Factory as RuleFactory;
use QUI\ERP\Shipping\Rules\ShippingRule;
use QUI\ERP\Shipping\Types\Factory as ShippingFactory;
use QUI\ERP\Shipping\Types\ShippingEntry;
use QUI\Permissions\Permission;
use Throwable;

/**
 * Optional MCP access to shipping configuration. Does not change orders or send notifications.
 */
class McpProvider implements ProviderInterface
{
    public const PERMISSION = 'quiqqer.shipping.mcp';

    public function register(Builder $serverBuilder): void
    {
        try {
            $this->checkPermission();
        } catch (Throwable) {
            return;
        }

        $this->registerEntries($serverBuilder, false);
        $this->registerEntries($serverBuilder, true);

        $emptySchema = Input::object([]);
        $serverBuilder->addTool(
            fn(): array | CallToolResult => $this->run($emptySchema, [], function (): array {
                $types = [];

                foreach (Shipping::getInstance()->getShippingTypes() as $Type) {
                    $types[] = $Type->toArray();
                }

                return ['types' => $types];
            }),
            name: 'shipping_types',
            description: 'Lists registered shipping types. Use the returned type value when creating a shipping method.',
            inputSchema: $emptySchema
        );
        $serverBuilder->addTool(
            fn(): array | CallToolResult => $this->run($emptySchema, [], function (): array {
                $statuses = [];

                foreach (ShippingStatus\Handler::getInstance()->getShippingStatusList() as $Status) {
                    $statuses[] = $Status->toArray();
                }

                return ['statuses' => $statuses];
            }),
            name: 'shipping_status_list',
            description: 'Lists configured shipping statuses with translations and automatic notification settings.',
            inputSchema: $emptySchema
        );
        $serverBuilder->addTool(
            fn(): array | CallToolResult => $this->run(
                $emptySchema,
                [],
                static fn(): array => ['carriers' => array_values(Tracking\Tracking::getActiveCarriers())]
            ),
            name: 'shipping_tracking_carriers',
            description: 'Lists active tracking carriers and their tracking URL prefixes. Does not query parcel status.',
            inputSchema: $emptySchema
        );
    }

    private function registerEntries(Builder $Builder, bool $rule): void
    {
        $prefix = $rule ? 'shipping_rule' : 'shipping';
        $subject = $rule ? 'shipping rule' : 'shipping method';
        $dataSchema = Input::data($rule);
        $dataSchema['minProperties'] = 1;
        $listSchema = Input::object([
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 2147483647, 'default' => 0],
            'active' => ['type' => 'boolean', 'description' => 'Omit to include active and inactive entries.'],
            'order' => [
                'type' => 'string',
                'enum' => ['id ASC', 'id DESC', 'priority ASC', 'priority DESC'],
                'default' => 'priority ASC'
            ]
        ]);
        $Builder->addTool(
            function (
                mixed $limit = 20,
                mixed $offset = 0,
                mixed $active = null,
                mixed $order = 'priority ASC'
            ) use (
                $listSchema,
                $rule
            ): array | CallToolResult {
                $arguments = compact('limit', 'offset', 'order');

                if ($active !== null) {
                    $arguments['active'] = $active;
                }

                return $this->run(
                    $listSchema,
                    $arguments,
                    fn(): array => $this->listEntries($rule, $limit, $offset, $active, $order)
                );
            },
            name: $prefix . '_list',
            description: 'Lists ' . $subject . ' configuration with pagination and a total count. '
                . 'Includes inactive entries by default. Prices and eligibility require an ERP order context.',
            inputSchema: $listSchema
        );

        $idSchema = Input::object(['id' => Input::id()], ['id']);
        $Builder->addTool(
            fn(mixed $id): array | CallToolResult => $this->run(
                $idSchema,
                ['id' => $id],
                fn(): array => $this->parseEntry($this->getEntry($rule, $id))
            ),
            name: $prefix . '_get',
            description: 'Reads one ' . $subject . ' configuration, including translations and configured restrictions.',
            inputSchema: $idSchema
        );

        $createProperties = ['data' => $dataSchema];
        $required = [];

        if (!$rule) {
            $createProperties['shippingType'] = [
                'type' => 'string',
                'minLength' => 1,
                'maxLength' => 255,
                'description' => 'Exact type returned by shipping_types.'
            ];
            $required[] = 'shippingType';
        }

        $createSchema = Input::object($createProperties, $required);

        if ($rule) {
            $create = fn(mixed $data = []): array | CallToolResult => $this->run(
                $createSchema,
                $data === [] ? [] : ['data' => $data],
                fn(): array => $this->createEntry(true, $data),
                ['create']
            );
        } else {
            $create = fn(mixed $shippingType, mixed $data = []): array | CallToolResult => $this->run(
                $createSchema,
                $data === [] ? ['shippingType' => $shippingType] : compact('shippingType', 'data'),
                fn(): array => $this->createEntry(false, $data, $shippingType),
                ['create']
            );
        }

        $Builder->addTool(
            $create,
            name: $prefix . '_create',
            description: 'Creates a ' . $subject . ', inactive by default. Optional data uses typed values and '
                . 'translation objects keyed by installed language codes. No order is changed.',
            inputSchema: $createSchema
        );

        $updateSchema = Input::object(['id' => Input::id(), 'data' => $dataSchema], ['id', 'data']);
        $Builder->addTool(
            fn(mixed $id, mixed $data): array | CallToolResult => $this->run(
                $updateSchema,
                compact('id', 'data'),
                fn(): array => $this->updateEntry($rule, $id, $data),
                ['edit']
            ),
            name: $prefix . '_update',
            description: 'Updates only supplied fields of a ' . $subject . '. Set active to true/false to '
                . 'activate/deactivate. Restriction arrays replace existing values; empty arrays clear them. '
                . 'Omitted fields and translations are preserved.',
            inputSchema: $updateSchema
        );
        $Builder->addTool(
            fn(mixed $id): array | CallToolResult => $this->run(
                $idSchema,
                ['id' => $id],
                fn(): array => $this->deleteEntry($rule, $id),
                ['delete']
            ),
            name: $prefix . '_delete',
            description: 'Permanently deletes a ' . $subject . '. Assigned rules must be detached from shipping '
                . 'methods before deletion. Requires the shipping delete permission.',
            inputSchema: $idSchema
        );
    }

    /** @param list<string> $permissions */
    private function checkPermission(array $permissions = []): void
    {
        $User = Server::getRequestUser();
        Permission::checkPermission(self::PERMISSION, $User);

        foreach ($permissions as $permission) {
            Permission::checkPermission('quiqqer.shipping.' . $permission, $User);
        }
    }

    /** @param array<string, mixed> $schema
     *  @param array<string, mixed> $arguments
     *  @param callable(): array<string, mixed> $operation
     *  @param list<string> $permissions
     *  @return array<string, mixed>|CallToolResult
     */
    private function run(
        array $schema,
        array $arguments,
        callable $operation,
        array $permissions = []
    ): array | CallToolResult {
        try {
            $this->checkPermission($permissions);
            Input::validate($arguments, $schema);

            return $operation();
        } catch (Throwable $Exception) {
            return ToolHelper::parseExceptionToResult($Exception);
        }
    }

    private function getFactory(bool $rule): Factory
    {
        return $rule ? RuleFactory::getInstance() : ShippingFactory::getInstance();
    }

    private function getEntry(bool $rule, int $id): ShippingEntry | ShippingRule
    {
        return $rule ? RuleFactory::getInstance()->getChild($id) : ShippingFactory::getInstance()->getChild($id);
    }

    /** @return array<string, mixed> */
    private function listEntries(bool $rule, int $limit, int $offset, ?bool $active, string $order): array
    {
        $Factory = $this->getFactory($rule);
        $query = [];

        if ($active !== null) {
            $query['where'] = ['active' => (int)$active];
        }

        $count = $Factory->countChildren($query);
        $query['order'] = $order;
        $query['limit'] = $offset . ',' . $limit;
        $entries = [];

        foreach ($Factory->getChildren($query) as $Entry) {
            if ($Entry instanceof ShippingEntry || $Entry instanceof ShippingRule) {
                $entries[] = $this->parseEntry($Entry);
            }
        }

        return [
            'entries' => $entries,
            'count' => $count,
            'limit' => $limit,
            'offset' => $offset
        ];
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function createEntry(bool $rule, array $data, string $shippingType = ''): array
    {
        $this->validateData($rule, $data);
        $attributes = $this->attributes($data);
        $attributes['active'] ??= 0;

        if ($rule) {
            $attributes['discount_type'] ??= RuleFactory::DISCOUNT_TYPE_ABS;
            $Entry = RuleFactory::getInstance()->createChild($attributes);
        } else {
            $Type = Shipping::getInstance()->getShippingType($shippingType);
            $attributes['shipping_type'] = $Type->getType();
            $Entry = ShippingFactory::getInstance()->createChild($attributes);
        }

        $this->setTranslations($Entry, $data);

        return $this->parseEntry($Entry);
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function updateEntry(bool $rule, int $id, array $data): array
    {
        $Entry = $this->getEntry($rule, $id);
        $this->validateData($rule, $data, $Entry->getAttributes());
        $Entry->setAttributes($this->attributes($data));
        $this->setTranslations($Entry, $data);
        $Entry->update();

        return $this->parseEntry($Entry);
    }

    /** @return array<string, mixed> */
    private function deleteEntry(bool $rule, int $id): array
    {
        $Entry = $this->getEntry($rule, $id);

        if ($rule) {
            foreach (ShippingFactory::getInstance()->getChildren() as $Shipping) {
                $ids = json_decode((string)$Shipping->getAttribute('shipping_rules'), true);

                if (is_array($ids) && in_array($id, $ids)) {
                    throw new QUI\Exception('Detach this rule from shipping methods before deleting it.');
                }
            }
        }

        $Entry->delete();

        return ['id' => $id, 'deleted' => true];
    }

    /** @param array<string, mixed> $data
     *  @param array<string, mixed> $current
     */
    private function validateData(bool $rule, array $data, array $current = []): void
    {
        foreach ($data['areas'] ?? [] as $id) {
            QUI\ERP\Areas\Handler::getInstance()->getChild($id);
        }

        foreach ($data['articles'] ?? [] as $id) {
            QUI\ERP\Products\Handler\Products::getProduct($id);
        }

        foreach ($data['categories'] ?? [] as $id) {
            QUI\ERP\Products\Handler\Categories::getCategory($id);
        }

        foreach ($data['payments'] ?? [] as $id) {
            QUI\ERP\Accounting\Payments\Payments::getInstance()->getPayment($id);
        }

        foreach ($data['user_groups'] ?? [] as $identifier) {
            $id = substr($identifier, 1);

            if ($identifier[0] === 'u') {
                QUI::getUsers()->get($id);
            } else {
                QUI::getGroups()->get($id);
            }
        }

        if (!$rule) {
            $ids = $data['shipping_rules'] ?? [];

            foreach ($ids as $id) {
                RuleFactory::getInstance()->getChild($id);
            }

            return;
        }

        foreach (['date_from', 'date_until'] as $key) {
            if (!empty($data[$key])) {
                $Date = \DateTimeImmutable::createFromFormat('!Y-m-d', $data[$key]);

                if ($Date === false || $Date->format('Y-m-d') !== $data[$key]) {
                    throw new QUI\Exception($key . ' must be a valid YYYY-MM-DD date.');
                }
            }
        }

        foreach ($data['unit_terms'] ?? [] as $term) {
            $allowedIds = array_map('intval', Shipping::getInstance()->getShippingRuleUnitFieldIds());

            if (!in_array($term['id'], $allowedIds, true)) {
                throw new QUI\Exception('Unit field is not enabled for shipping rules.');
            }

            if (
                $term['id'] === QUI\ERP\Products\Handler\Fields::FIELD_WEIGHT
                && !QUI\ERP\Products\Utils\Fields::isWeight($term['unit'])
            ) {
                throw new QUI\Exception('Unsupported weight unit.');
            }

            if (isset($term['value2']) !== isset($term['term2'])) {
                throw new QUI\Exception('A second unit boundary needs both value2 and term2.');
            }
        }

        $data = array_replace($current, $data);

        foreach (['purchase_quantity', 'purchase_value', 'date'] as $range) {
            $from = $data[$range . '_from'] ?? '';
            $until = $data[$range . '_until'] ?? '';

            if ($from && $until && $from > $until) {
                throw new QUI\Exception($range . ' lower boundary must not exceed its upper boundary.');
            }
        }
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        unset($data['title'], $data['workingTitle'], $data['description']);

        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $data[$key] = (int)$value;
            } elseif (is_array($value)) {
                $data[$key] = in_array($key, ['shipping_rules', 'unit_terms'], true)
                    ? json_encode($value, JSON_THROW_ON_ERROR)
                    : implode(',', $value);
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function setTranslations(ShippingEntry | ShippingRule $Entry, array $data): void
    {
        if (isset($data['title'])) {
            $Entry->setTitle($data['title']);
        }

        if (isset($data['workingTitle'])) {
            $Entry->setWorkingTitle($data['workingTitle']);
        }

        if ($Entry instanceof ShippingEntry && isset($data['description'])) {
            $Entry->setDescription($data['description']);
        }
    }

    /** @return array<string, mixed> */
    private function parseEntry(ShippingEntry | ShippingRule $Entry): array
    {
        // Configuration only: do not calculate a price without a customer/order or expose arbitrary provider attributes.
        $attributes = $Entry->getAttributes();
        $rule = $Entry instanceof ShippingRule;
        $result = array_intersect_key($attributes, Input::data($rule)['properties']);
        $result['id'] = $Entry->getId();
        $result['active'] = $Entry->isActive();
        $result['priority'] = (int)($attributes['priority'] ?? 0);
        $result['title'] = [];
        $result['workingTitle'] = [];
        $suffix = $rule ? '.rule.' : '.';

        foreach (['areas', 'articles', 'categories', 'payments', 'user_groups'] as $field) {
            if (array_key_exists($field, $result)) {
                $values = array_values(array_filter(
                    explode(',', (string)$result[$field]),
                    static fn(string $value): bool => $value !== ''
                ));
                $result[$field] = $field === 'user_groups' ? $values : array_map('intval', $values);
            }
        }

        if ($rule) {
            $result['unit_terms'] = $Entry->getUnitTerms() ?: [];
            $result['discount'] = $Entry->getDiscount();
            $result['discount_type'] = $Entry->getDiscountType();
            $result['articles_only'] = (bool)($attributes['articles_only'] ?? false);
            $result['no_rule_after'] = $Entry->noRulesAfter();
        }

        foreach (QUI::availableLanguages() as $language) {
            foreach (['title', 'workingTitle'] as $field) {
                $result[$field][$language] = QUI::getLocale()->getByLang(
                    $language,
                    'quiqqer/shipping',
                    'shipping.' . $Entry->getId() . $suffix . $field
                );
            }

            if (!$rule) {
                $result['description'][$language] = QUI::getLocale()->getByLang(
                    $language,
                    'quiqqer/shipping',
                    'shipping.' . $Entry->getId() . '.description'
                );
            }
        }

        if (!$rule) {
            $result['shippingType'] = $attributes['shipping_type'] ?? '';
            $result['shipping_rules'] = json_decode((string)($attributes['shipping_rules'] ?? ''), true) ?? [];
        }

        return $result;
    }
}
