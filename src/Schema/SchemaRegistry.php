<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Schema;

use Infocyph\ReqShield\Exceptions\FrozenSchemaRegistryException;
use Infocyph\ReqShield\Exceptions\InvalidSchemaException;
use Infocyph\ReqShield\Support\RuleDefinitionSnapshot;
use Infocyph\ReqShield\Validator;

final class SchemaRegistry
{
    private bool $frozen = false;

    /** @var array<string,array<string,mixed>> */
    private array $schemas = [];

    /** @param array<array-key,mixed> $schemas */
    public function __construct(array $schemas = [])
    {
        foreach ($schemas as $name => $schema) {
            if (!is_string($name) || !is_array($schema)) {
                throw InvalidSchemaException::forField(
                    'schema',
                    'Registry entries must map schema names to rule arrays.',
                );
            }

            $this->define($name, $schema);
        }
    }

    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        $schemas = [];

        foreach ($this->schemas as $name => $schema) {
            $schemas[$name] = $this->snapshotSchema($schema);
        }

        return $schemas;
    }

    /** @param array<int|string,mixed> $schema */
    public function define(string $name, array $schema): self
    {
        $this->assertMutable('define');
        $name = $this->name($name);

        if (array_key_exists($name, $this->schemas)) {
            throw InvalidSchemaException::forField(
                'schema',
                "Schema already exists: {$name}",
            );
        }

        $this->schemas[$name] = $this->snapshotSchema($this->normalizeSchema($schema));

        return $this;
    }

    /** @param array<int|string,mixed> $schema */
    public function extend(string $name, array $schema): self
    {
        $this->assertMutable('extend');
        $name = $this->name($name);
        $incoming = $this->normalizeSchema($schema);

        $this->schemas[$name] = $this->snapshotSchema($this->normalizeSchema(
            Validator::composeSchemas(
                $this->schemas[$name] ?? [],
                $incoming,
            ),
        ));

        return $this;
    }

    public function freeze(): self
    {
        $this->frozen = true;

        return $this;
    }

    /** @return array<string,mixed> */
    public function get(string $name): array
    {
        $name = $this->name($name);
        if (!array_key_exists($name, $this->schemas)) {
            throw InvalidSchemaException::forField(
                'schema',
                "Unknown schema: {$name}",
            );
        }

        return $this->snapshotSchema($this->schemas[$name]);
    }

    public function has(string $name): bool
    {
        return array_key_exists($this->name($name), $this->schemas);
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    public function remove(string $name): self
    {
        $this->assertMutable('remove');
        $name = $this->name($name);

        if (!array_key_exists($name, $this->schemas)) {
            throw InvalidSchemaException::forField(
                'schema',
                "Unknown schema: {$name}",
            );
        }

        unset($this->schemas[$name]);

        return $this;
    }

    /** @param array<int|string,mixed> $schema */
    public function replace(string $name, array $schema): self
    {
        $this->assertMutable('replace');
        $name = $this->name($name);

        if (!array_key_exists($name, $this->schemas)) {
            throw InvalidSchemaException::forField(
                'schema',
                "Unknown schema: {$name}",
            );
        }

        $this->schemas[$name] = $this->snapshotSchema($this->normalizeSchema($schema));

        return $this;
    }

    /** @return array<string,mixed>|null */
    public function schema(string $name): ?array
    {
        $name = $this->name($name);

        return isset($this->schemas[$name])
            ? $this->snapshotSchema($this->schemas[$name])
            : null;
    }

    private function assertMutable(string $operation): void
    {
        if ($this->frozen) {
            throw FrozenSchemaRegistryException::forOperation($operation);
        }
    }

    private function name(string $name): string
    {
        $normalized = trim($name);
        if ($normalized === '') {
            throw InvalidSchemaException::forField(
                'schema',
                'Schema name must be a non-empty string.',
            );
        }

        return $normalized;
    }

    /**
     * @param array<int|string,mixed> $schema
     * @return array<string,mixed>
     */
    private function normalizeSchema(array $schema): array
    {
        $normalized = [];

        foreach ($schema as $field => $rule) {
            if (!is_string($field) || trim($field) === '') {
                throw InvalidSchemaException::forField(
                    (string) $field,
                    'Schema field names must be non-empty strings.',
                );
            }

            $normalized[$field] = $rule;
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $schema
     * @return array<string,mixed>
     */
    private function snapshotSchema(array $schema): array
    {
        return RuleDefinitionSnapshot::map($schema);
    }
}
