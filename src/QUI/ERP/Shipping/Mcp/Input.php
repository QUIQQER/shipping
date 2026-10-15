<?php

namespace QUI\ERP\Shipping\Mcp;

use QUI;

/**
 * Shared schemas and runtime validation for the optional MCP API.
 */
final class Input
{
    /** @param array<string, mixed> $properties
     *  @param list<string> $required
     *  @return array<string, mixed>
     */
    public static function object(array $properties, array $required = []): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties === [] ? new \stdClass() : $properties,
            'required' => $required
        ];
    }

    /** @return array<string, mixed> */
    public static function id(): array
    {
        return ['type' => 'integer', 'minimum' => 1, 'maximum' => PHP_INT_MAX];
    }

    /** @return array<string, mixed> */
    public static function data(bool $rule): array
    {
        $languages = [];

        foreach (QUI::availableLanguages() as $language) {
            $languages[$language] = ['type' => 'string', 'maxLength' => 10000];
        }

        $translations = self::object($languages);
        $translations['minProperties'] = 1;
        $translations['description'] = 'Translations keyed by an installed language code, e.g. {"en":"Standard"}.';
        $ids = [
            'type' => 'array',
            'items' => self::id(),
            'maxItems' => 1000,
            'uniqueItems' => true
        ];
        $properties = [
            'title' => $translations,
            'workingTitle' => $translations,
            'active' => ['type' => 'boolean'],
            'priority' => ['type' => 'integer', 'minimum' => -2147483648, 'maximum' => 2147483647],
            'areas' => $ids,
            'articles' => $ids,
            'categories' => $ids
        ];
        $properties['user_groups'] = [
            'type' => 'array',
            'items' => [
                'type' => 'string',
                'pattern' => '^[ug]([1-9][0-9]*|[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})$',
                'maxLength' => 64
            ],
            'maxItems' => 1000,
            'uniqueItems' => true,
            'description' => 'Allowed users/groups, e.g. ["u123", "g456"]. Prefix UUIDs with u/g too.'
        ];

        if (!$rule) {
            $properties['description'] = $translations;
            $properties['payments'] = $ids;
            $properties['shipping_rules'] = $ids;
            $properties['shipping_rules']['description'] = 'Replaces the assigned rule IDs. An empty array removes all.';

            return self::object($properties);
        }

        $properties['discount'] = ['type' => 'number', 'minimum' => 0];
        $properties['discount_type'] = [
            'type' => 'integer',
            'enum' => [0, 1, 2],
            'description' => '0: absolute amount, 1: percentage of shipping price, 2: percentage of order net total.'
        ];
        $properties['articles_only'] = ['type' => 'boolean'];
        $properties['no_rule_after'] = ['type' => 'boolean'];

        foreach (['from', 'until'] as $bound) {
            $properties['purchase_quantity_' . $bound] = [
                'type' => 'integer',
                'minimum' => 0,
                'maximum' => 2147483647
            ];
            $properties['purchase_value_' . $bound] = ['type' => 'number', 'minimum' => 0];
            $properties['date_' . $bound] = [
                'type' => 'string',
                'pattern' => '^([0-9]{4}-[0-9]{2}-[0-9]{2})?$',
                'description' => 'YYYY-MM-DD or an empty string to remove the boundary.'
            ];
        }

        $comparison = ['type' => 'string', 'enum' => ['=', 'gt', 'egt', 'lt', 'elt']];
        $properties['unit_terms'] = [
            'type' => 'array',
            'maxItems' => 100,
            'items' => self::object([
                'id' => self::id(),
                'unit' => ['type' => 'string', 'maxLength' => 32],
                'value' => ['type' => 'number', 'minimum' => 0],
                'term' => $comparison,
                'value2' => ['type' => 'number', 'minimum' => 0],
                'term2' => $comparison
            ], ['id', 'unit', 'value', 'term'])
        ];

        return self::object($properties);
    }

    /**
     * Validate raw values without PHP's scalar coercion or silently dropping unknown fields.
     *
     * @param array<string, mixed> $schema
     */
    public static function validate(mixed $value, array $schema, string $path = 'arguments'): void
    {
        $valid = match ($schema['type']) {
            'object' => is_array($value) && ($value === [] || !array_is_list($value)),
            'array' => is_array($value) && array_is_list($value),
            'integer' => is_int($value),
            'number' => (is_int($value) || is_float($value)) && is_finite((float)$value),
            'boolean' => is_bool($value),
            'string' => is_string($value),
            default => false
        };

        if (!$valid) {
            throw new QUI\Exception($path . ' must be of type ' . $schema['type'] . '.');
        }

        if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            throw new QUI\Exception($path . ' contains an unsupported value.');
        }

        if (is_array($value)) {
            self::validateArray($value, $schema, $path);
        }

        if (is_int($value) || is_float($value)) {
            if (
                (isset($schema['minimum']) && $value < $schema['minimum'])
                || (isset($schema['maximum']) && $value > $schema['maximum'])
            ) {
                throw new QUI\Exception($path . ' is outside the allowed range.');
            }
        }

        if (is_string($value)) {
            self::validateString($value, $schema, $path);
        }
    }

    /** @param array<array-key, mixed> $value
     *  @param array<string, mixed> $schema
     */
    private static function validateArray(array $value, array $schema, string $path): void
    {
        if ($schema['type'] === 'object') {
            $properties = (array)$schema['properties'];

            foreach ($schema['required'] as $key) {
                if (!array_key_exists($key, $value)) {
                    throw new QUI\Exception($path . '.' . $key . ' is required.');
                }
            }

            if (count($value) < ($schema['minProperties'] ?? 0)) {
                throw new QUI\Exception($path . ' must not be empty.');
            }

            foreach ($value as $key => $entry) {
                if (!isset($properties[$key])) {
                    throw new QUI\Exception($path . ' contains an unknown field or language: ' . $key);
                }

                self::validate($entry, $properties[$key], $path . '.' . $key);
            }

            return;
        }

        if (count($value) > ($schema['maxItems'] ?? 1000)) {
            throw new QUI\Exception($path . ' contains too many entries.');
        }

        if (!empty($schema['uniqueItems']) && count(array_unique($value, SORT_REGULAR)) !== count($value)) {
            throw new QUI\Exception($path . ' contains duplicate entries.');
        }

        foreach ($value as $key => $entry) {
            self::validate($entry, $schema['items'], $path . '.' . $key);
        }
    }

    /** @param array<string, mixed> $schema */
    private static function validateString(string $value, array $schema, string $path): void
    {
        if (
            mb_strlen($value) < ($schema['minLength'] ?? 0)
            || mb_strlen($value) > ($schema['maxLength'] ?? 10000)
            || str_contains($value, "\0")
        ) {
            throw new QUI\Exception($path . ' has an invalid length or contains a null byte.');
        }

        if (isset($schema['pattern']) && preg_match('~' . $schema['pattern'] . '~D', $value) !== 1) {
            throw new QUI\Exception($path . ' has an invalid format.');
        }
    }
}
