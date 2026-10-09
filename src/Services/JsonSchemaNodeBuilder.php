<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Services;

/** @phpstan-type JsonNode array<int|string, mixed> */
final class JsonSchemaNodeBuilder
{
    /**
     * @param JsonNode $schema
     * @param JsonNode $property
     */
    public function addProperty(
        array &$schema,
        string $path,
        array $property,
        bool $required,
    ): void {
        $segments = explode('.', $path);
        $this->addPropertyAtPath($schema, $segments, 0, $property, $required);
    }

    /**
     * @param JsonNode $property
     * @param array<int, mixed> $params
     */
    public function applyEnumConstraint(
        array &$property,
        string $ruleName,
        array $params,
    ): bool {
        if ($ruleName === 'in' && $params !== []) {
            $property['enum'] = array_values($params);

            return true;
        }

        if (
            $ruleName !== 'enum'
            || !isset($params[0])
            || !is_string($params[0])
            || !enum_exists($params[0])
        ) {
            return false;
        }

        $enumClass = $params[0];
        $cases = $enumClass::cases();
        if ($cases === []) {
            return false;
        }

        if (is_subclass_of($enumClass, \BackedEnum::class)) {
            $values = [];
            foreach ($cases as $case) {
                if ($case instanceof \BackedEnum) {
                    $values[] = $case->value;
                }
            }
            $property['enum'] = $values;

            return true;
        }

        $property['enum'] = array_map(
            static fn(\UnitEnum $case): string => $case->name,
            $cases,
        );

        return true;
    }

    /**
     * @param JsonNode $property
     * @param array<int,mixed> $params
     */
    public function applyReqShieldExtension(array &$property, string $ruleName, array $params): void
    {
        if ($ruleName === 'active_url') {
            $property['x-reqshield-active-url'] = true;

            return;
        }

        if (in_array($ruleName, ['exists', 'unique'], true)) {
            $property['x-reqshield-' . $ruleName] = [
                'table' => $params[0] ?? null,
                'column' => $params[1] ?? null,
            ];

            return;
        }

        if ($ruleName === 'date_format') {
            $property['x-reqshield-date-format'] = $params[0] ?? null;

            return;
        }

        $crossFieldRules = [
            'same', 'different', 'gt', 'gte', 'lt', 'lte', 'in_array',
            'date_equals', 'before', 'before_or_equal', 'after', 'after_or_equal',
        ];
        if (in_array($ruleName, $crossFieldRules, true)) {
            $property['x-reqshield-' . str_replace('_', '-', $ruleName)] = $params[0] ?? true;

            return;
        }

        if ($ruleName === 'confirmed') {
            $property['x-reqshield-confirmed-by'] = true;

            return;
        }

        if (str_starts_with($ruleName, 'required_') || str_starts_with($ruleName, 'present_')) {
            $property['x-reqshield-' . str_replace('_', '-', $ruleName)] = $params;
        }
    }

    /** @param JsonNode $node */
    public function normalizeNode(array &$node): void
    {
        if (isset($node['required']) && is_array($node['required'])) {
            $required = array_values(array_filter(
                $node['required'],
                is_string(...),
            ));
            $node['required'] = array_values(array_unique($required));
        }

        if (isset($node['properties']) && is_array($node['properties'])) {
            foreach ($node['properties'] as &$child) {
                if (is_array($child)) {
                    $this->normalizeNode($child);
                }
            }

            unset($child);
        }

        if (isset($node['items']) && is_array($node['items'])) {
            $this->normalizeNode($node['items']);
        }
    }

    /**
     * @param JsonNode $node
     * @return JsonNode
     */
    protected function &ensureItemsNode(array &$node): array
    {
        $this->ensureArrayNode($node);

        if (!isset($node['items']) || !is_array($node['items'])) {
            $node['items'] = ['type' => 'object', 'properties' => []];
        }

        return $node['items'];
    }

    /**
     * @param JsonNode $node
     * @return JsonNode
     */
    protected function &ensurePropertiesNode(array &$node): array
    {
        $this->ensureObjectNode($node);

        if (!isset($node['properties']) || !is_array($node['properties'])) {
            $node['properties'] = [];
        }

        return $node['properties'];
    }

    /**
     * @param JsonNode $node
     * @param array<int, string> $segments
     * @param JsonNode $property
     */
    protected function addPropertyAtPath(
        array &$node,
        array $segments,
        int $index,
        array $property,
        bool $required,
    ): void {
        $segment = $segments[$index] ?? null;
        if (!is_string($segment)) {
            return;
        }

        $isLast = $index === count($segments) - 1;

        if ($segment === '*') {
            $items = &$this->ensureItemsNode($node);
            if ($isLast) {
                $items = $property;

                return;
            }

            $this->addPropertyAtPath($items, $segments, $index + 1, $property, $required);

            return;
        }

        $properties = &$this->ensurePropertiesNode($node);

        if ($isLast) {
            $properties[$segment] = $property;
            if ($required) {
                $this->appendRequiredSegment($node, $segment);
            }

            return;
        }

        if ($required) {
            $this->appendRequiredSegment($node, $segment);
        }

        if (!isset($properties[$segment]) || !is_array($properties[$segment])) {
            $properties[$segment] = ['type' => 'object', 'properties' => []];
        }

        $child = &$properties[$segment];
        $this->addPropertyAtPath($child, $segments, $index + 1, $property, $required);
    }

    /** @param JsonNode $node */
    protected function appendRequiredSegment(array &$node, string $segment): void
    {
        if (!isset($node['required']) || !is_array($node['required'])) {
            $node['required'] = [];
        }

        $node['required'][] = $segment;
    }

    /** @param JsonNode $node */
    protected function ensureArrayNode(array &$node): void
    {
        $node['type'] = 'array';
        if (!isset($node['items']) || !is_array($node['items'])) {
            $node['items'] = ['type' => 'object', 'properties' => []];
        }
    }

    /** @param JsonNode $node */
    protected function ensureObjectNode(array &$node): void
    {
        if (($node['type'] ?? 'object') !== 'object') {
            $node['type'] = 'object';
        }

        if (!isset($node['properties']) || !is_array($node['properties'])) {
            $node['properties'] = [];
        }
    }
}
