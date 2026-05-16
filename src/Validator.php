<?php

declare(strict_types=1);

namespace Infocyph\ReqShield;

use Infocyph\ReqShield\Concerns\HasValidatorInternals;
use Infocyph\ReqShield\Concerns\HasValidatorRuntime;
use Infocyph\ReqShield\Concerns\HasValidatorSchemaCasting;
use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Exceptions\InvalidRuleException;
use Infocyph\ReqShield\Exceptions\ValidationException;
use Infocyph\ReqShield\Executors\BatchExecutor;
use Infocyph\ReqShield\Services\JsonSchemaExporter;
use Infocyph\ReqShield\Services\MessageTokenBuilder;
use Infocyph\ReqShield\Services\SanitizerMapApplier;
use Infocyph\ReqShield\Support\FieldAlias;
use Infocyph\ReqShield\Support\RuleExpressionParser;
use Infocyph\ReqShield\Support\SchemaCompiler;
use Infocyph\ReqShield\Support\ValidationPlan;
use Infocyph\ReqShield\Support\ValidationResult;
use Infocyph\ReqShield\Support\WildcardPath;

/**
 * @phpstan-consistent-constructor
 */
class Validator
{
    use HasValidatorInternals;
    use HasValidatorRuntime;
    use HasValidatorSchemaCasting;

    protected const MAX_COMPILED_SCHEMA_CACHE = 64;

    protected const MAX_WILDCARD_SCHEMA_CACHE = 64;

    /** @var array<int|string,array<int|string,mixed>> */
    protected static array $fragments = [];

    protected BatchExecutor $batchExecutor;

    /** @var array<string,mixed> */
    protected array $casts = [];

    /** @var array<string,ValidationPlan> */
    protected array $compiledSchemaCache = [];

    protected SchemaCompiler $compiler;

    /** @var array<int,array{field:string,rules:string|array<int,mixed>,condition:callable}> */
    protected array $conditionalRules = [];

    /** @var array<string,string> */
    protected array $customMessageExact = [];

    /** @var array<string,string> */
    protected array $customMessages = [];

    /** @var array<string,string> */
    protected array $customMessageWildcard = [];

    /** @var array<int,array{pattern:string,message:string}> */
    protected array $customMessageWildcardPatterns = [];

    protected ?string $dtoClass = null;

    protected bool $failFast = true;

    /** @var array<string,string> */
    protected array $fieldAliases = [];

    protected FieldAlias $fieldAliasResolver;

    protected JsonSchemaExporter $jsonSchemaExporter;

    protected string $locale = 'en';

    protected bool $localeMessagesEnabled = false;

    /** @var array<string,array<string,mixed>> */
    protected array $localePacks = [];

    protected MessageTokenBuilder $messageTokenBuilder;

    protected string $nestedFlattenMode = 'all';

    protected bool $nestedValidation = false;

    /** @var array<int|string,mixed> */
    protected array $rules;

    protected string $rulesCacheKey = '';

    protected SanitizerMapApplier $sanitizerMapApplier;

    /** @var array<string,mixed> */
    protected array $sanitizers = [];

    /** @var array<int|string,mixed> */
    protected array $schema;

    /** @var array<string,mixed> */
    protected array $schemaCasts = [];

    /** @var array<string,mixed> */
    protected array $schemaSanitizers = [];

    protected bool $stopOnFirstError = false;

    protected bool $throwOnFailure = false;

    protected ValidationPlan $validationPlan;

    /** @var array<int,array<string,mixed>> */
    protected array $whenCallbacks = [];

    /** @var array<string,ValidationPlan> */
    protected array $wildcardSchemaCache = [];

    /**
     * @param array<int|string,mixed> $rules
     */
    public function __construct(array $rules, ?DatabaseProvider $db = null)
    {
        if (empty($rules)) {
            throw InvalidRuleException::invalidFormat(
                'rules',
                'Rules array cannot be empty',
            );
        }

        foreach ($rules as $field => $rule) {
            if (!is_string($field)) {
                throw InvalidRuleException::invalidFormat(
                    (string) $field,
                    'Field names must be strings',
                );
            }

            if (!is_string($rule) && !is_array($rule)) {
                throw InvalidRuleException::invalidFormat(
                    $field,
                    'Rules must be string or array',
                );
            }
        }

        [$normalizedRules, $schemaSanitizers, $schemaCasts] = $this->normalizeRuleDefinitions($rules);
        $this->rules = $normalizedRules;
        $this->schemaSanitizers = $schemaSanitizers;
        $this->schemaCasts = $schemaCasts;
        $this->rulesCacheKey = $this->buildRulesCacheKey($normalizedRules);
        $this->localePacks = $this->defaultLocalePacks();
        $this->compiler = new SchemaCompiler();
        $this->schema = $this->normalizeCompiledSchema(
            $this->compiler->compile($normalizedRules),
        );
        $this->validationPlan = new ValidationPlan($this->schema);
        $this->fieldAliasResolver = new FieldAlias($this->fieldAliases);
        $this->messageTokenBuilder = new MessageTokenBuilder();
        $this->jsonSchemaExporter = new JsonSchemaExporter();
        $this->sanitizerMapApplier = new SanitizerMapApplier();
        $this->batchExecutor = new BatchExecutor($db);
    }

    /**
     * @param array<int|string,mixed> ...$schemas
     *
     * @return array<int|string,mixed>
     */
    public static function composeSchemas(array ...$schemas): array
    {
        $composed = [];

        foreach ($schemas as $schema) {
            $composed = static::mergeComposedSchema($composed, $schema);
        }

        return $composed;
    }

    /** @param array<int|string,mixed> $rules */
    public static function defineFragment(string $name, array $rules): void
    {
        static::$fragments[$name] = $rules;
    }

    /** @return array<int|string,mixed> */
    public static function fragment(string $name, string $prefix = ''): array
    {
        if (!isset(static::$fragments[$name])) {
            throw new InvalidRuleException("Unknown schema fragment: {$name}");
        }

        if ($prefix === '') {
            return static::$fragments[$name];
        }

        $prefixed = [];
        foreach (static::$fragments[$name] as $field => $rules) {
            if (!is_string($field)) {
                continue;
            }

            $prefixed["{$prefix}.{$field}"] = $rules;
        }

        return $prefixed;
    }

    public static function hasFragment(string $name): bool
    {
        return array_key_exists($name, static::$fragments);
    }

    /**
     * @param array<int|string,mixed> $rules
     */
    public static function make(
        array $rules,
        ?DatabaseProvider $db = null,
    ): self {
        return new static($rules, $db);
    }

    /** @param array<string,string> $messages */
    public function addLocalePack(string $locale, array $messages): self
    {
        $this->localePacks[$locale] = $messages;
        $this->localeMessagesEnabled = true;

        return $this;
    }

    public function enableNestedValidation(bool $flattenAll = true): self
    {
        $this->nestedValidation = true;
        $this->nestedFlattenMode = $flattenAll ? 'all' : 'required';

        return $this;
    }

    /** @return array<int|string,mixed> */
    public function exportSchema(string $format = 'json_schema'): array
    {
        $jsonSchema = $this->exportJsonSchema();

        return match ($format) {
            'json_schema' => $jsonSchema,
            'openapi' => [
                'type' => 'object',
                'properties' => $jsonSchema['properties'],
                'required' => $jsonSchema['required'],
            ],
            'introspection' => $this->schemaIntrospection(),
            default => throw new InvalidRuleException("Unsupported schema export format: {$format}"),
        };
    }

    /** @return array<int|string,mixed> */
    public function getSchema(): array
    {
        return $this->schema;
    }

    /** @return array<int|string,mixed> */
    public function getSchemaStats(): array
    {
        $stats = [
            'total_fields' => count($this->schema),
            'fields' => [],
        ];

        foreach ($this->schema as $field => $node) {
            if (!is_string($field) || !$node instanceof \Infocyph\ReqShield\Support\ValidationNode) {
                continue;
            }

            $stats['fields'][$field] = $node->getStats();
        }

        return $stats;
    }

    public function registerRule(string $name, string $class): self
    {
        $this->compiler->registerRule($name, $class);
        $this->schema = $this->normalizeCompiledSchema(
            $this->compiler->compile($this->rules),
        );
        $this->validationPlan = new ValidationPlan($this->schema);
        $this->compiledSchemaCache = [];
        $this->wildcardSchemaCache = [];

        return $this;
    }

    /** @return array<string,array<string,mixed>> */
    public function schemaIntrospection(): array
    {
        $meta = [];

        foreach ($this->schema as $field => $node) {
            if (!is_string($field) || !$node instanceof \Infocyph\ReqShield\Support\ValidationNode) {
                continue;
            }

            $meta[$field] = [
                'rules' => $node->getAllRuleNames(),
                'optional' => $node->isOptional,
                'implicit' => $node->requiresValidationWhenMissing,
                'sanitizers' => $this->schemaSanitizers[$field] ?? null,
                'cast' => $this->schemaCasts[$field] ?? null,
            ];
        }

        return $meta;
    }

    /** @param array<string,mixed> $casts */
    public function setCasts(array $casts): self
    {
        $this->casts = $casts;

        return $this;
    }

    /** @param array<int|string,mixed> $messages */
    public function setCustomMessages(array $messages): self
    {
        $this->customMessages = [];
        $this->customMessageExact = [];
        $this->customMessageWildcard = [];
        $this->customMessageWildcardPatterns = [];

        foreach ($messages as $key => $message) {
            if (!is_string($key) || !is_string($message)) {
                continue;
            }

            $this->customMessages[$key] = $message;

            if (str_contains($key, '*')) {
                $this->customMessageWildcard[$key] = $message;
                $this->customMessageWildcardPatterns[] = [
                    'pattern' => WildcardPath::toRegex($key),
                    'message' => $message,
                ];

                continue;
            }

            $this->customMessageExact[$key] = $message;
        }

        return $this;
    }

    public function setDtoClass(?string $class): self
    {
        $this->dtoClass = $class;

        return $this;
    }

    public function setFailFast(bool $failFast): self
    {
        $this->failFast = $failFast;

        return $this;
    }

    /** @param array<string,string> $aliases */
    public function setFieldAliases(array $aliases): self
    {
        $this->fieldAliases = $aliases;
        $this->fieldAliasResolver->setBatch($aliases, true);

        return $this;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        $this->localeMessagesEnabled = true;

        return $this;
    }

    /** @param array<string,array<string,mixed>> $packs */
    public function setLocalePacks(array $packs): self
    {
        $this->localePacks = $packs;
        $this->localeMessagesEnabled = true;

        return $this;
    }

    public function setNestedFlattenMode(string $mode): self
    {
        if (!in_array($mode, ['all', 'required'], true)) {
            throw new InvalidRuleException(
                "Invalid nested flatten mode: {$mode}",
            );
        }

        $this->nestedFlattenMode = $mode;

        return $this;
    }

    /** @param array<string,string|callable|array<int,string|callable>> $sanitizers */
    public function setSanitizers(array $sanitizers): self
    {
        $this->sanitizers = $sanitizers;

        return $this;
    }

    public function setStopOnFirstError(bool $stop): self
    {
        $this->stopOnFirstError = $stop;

        return $this;
    }

    /** @param string|array<int,mixed> $rules */
    public function sometimes(
        string $field,
        string|array $rules,
        callable $condition,
    ): self {
        $this->conditionalRules[] = [
            'field' => $field,
            'rules' => $rules,
            'condition' => $condition,
        ];

        return $this;
    }

    public function throwOnFailure(bool $throw = true): self
    {
        $this->throwOnFailure = $throw;

        return $this;
    }

    public function useFragment(string $name, string $prefix = ''): self
    {
        $fragment = array_filter(
            static::fragment($name, $prefix),
            is_string(...),
            ARRAY_FILTER_USE_KEY,
        );

        [$fragmentRules, $fragmentSanitizers, $fragmentCasts] = $this->normalizeRuleDefinitions(
            $fragment,
        );

        $this->rules = static::composeSchemas($this->rules, $fragmentRules);
        $this->schemaSanitizers = array_merge($this->schemaSanitizers, $fragmentSanitizers);
        $this->schemaCasts = array_merge($this->schemaCasts, $fragmentCasts);
        $this->rulesCacheKey = $this->buildRulesCacheKey($this->rules);
        $this->schema = $this->normalizeCompiledSchema(
            $this->compiler->compile($this->rules),
        );
        $this->validationPlan = new ValidationPlan($this->schema);
        $this->fieldAliasResolver->setBatch($this->fieldAliases, true);
        $this->compiledSchemaCache = [];
        $this->wildcardSchemaCache = [];

        return $this;
    }

    /** @param array<int|string,mixed> $data */
    public function validate(array $data): ValidationResult
    {
        [$data, $plan] = $this->prepareValidationDataAndSchema($data);
        $context = $this->initializeValidationContext();
        $this->validateResolvedFields($data, $plan, $context);
        $this->executeBatchedRules($context);
        $result = $this->buildValidationResult($context);
        $errors = isset($context['errors']) && is_array($context['errors'])
            ? $this->normalizeErrorMap($context['errors'])
            : [];
        $this->throwIfValidationShouldFail($result, $errors);

        return $result;
    }

    public function when(
        bool|callable $condition,
        callable $callback,
        ?callable $default = null,
    ): self {
        $this->whenCallbacks[] = [
            'condition' => $condition,
            'callback' => $callback,
            'default' => $default,
        ];

        return $this;
    }

    /**
     * @param array<int|string,mixed> $composed
     * @param array<int|string,mixed> $schema
     *
     * @return array<int|string,mixed>
     */
    protected static function mergeComposedSchema(array $composed, array $schema): array
    {
        foreach ($schema as $field => $rules) {
            if (!is_string($field)) {
                continue;
            }

            $existing = $composed[$field] ?? [];
            $composed[$field] = array_merge(
                static::normalizeComposedRuleSet($existing),
                static::normalizeComposedRuleSet($rules),
            );
        }

        return $composed;
    }

    /** @return array<int|string,mixed> */
    protected static function normalizeComposedRuleSet(mixed $rules): array
    {
        if (is_array($rules)) {
            return array_values($rules);
        }

        if (is_string($rules)) {
            return RuleExpressionParser::splitRules($rules);
        }

        return [];
    }

    /** @param array<int|string,mixed> $context */
    protected function buildValidationResult(array $context): ValidationResult
    {
        $errors = isset($context['errors']) && is_array($context['errors'])
            ? $this->normalizeErrorMap($context['errors'])
            : [];
        $validated = isset($context['validated']) && is_array($context['validated']) ? $context['validated'] : [];
        $failures = isset($context['failures']) && is_array($context['failures']) ? $context['failures'] : [];

        return new ValidationResult(
            $errors,
            $validated,
            $failures,
            $this->applyCasts($validated),
            $this->dtoClass,
        );
    }

    /**
     * @param array<int|string,mixed> $schema
     *
     * @return array<string,\Infocyph\ReqShield\Support\ValidationNode>
     */
    protected function normalizeCompiledSchema(array $schema): array
    {
        $normalized = [];

        foreach ($schema as $field => $node) {
            if (!is_string($field) || !$node instanceof \Infocyph\ReqShield\Support\ValidationNode) {
                continue;
            }

            $normalized[$field] = $node;
        }

        return $normalized;
    }

    /**
     * @param array<int|string,mixed> $errors
     * @return array<string,array<int,string>>
     */
    protected function normalizeErrorMap(array $errors): array
    {
        $normalized = [];

        foreach ($errors as $field => $messages) {
            if (!is_string($field) || !is_array($messages)) {
                continue;
            }

            $normalized[$field] = array_values(array_filter(
                $messages,
                is_string(...),
            ));
        }

        return $normalized;
    }

    /**
     * @param array<int|string,mixed> $data
     *
     * @return array{0:array<int|string,mixed>,1:ValidationPlan}
     */
    protected function prepareValidationDataAndSchema(array $data): array
    {
        if (!empty($this->sanitizers) || !empty($this->schemaSanitizers)) {
            $data = $this->applySanitizers($data);
        }

        $activeRules = $this->prepareRuntimeRules($data);
        $activeRulesCacheKey = $activeRules === $this->rules
            ? $this->rulesCacheKey
            : $this->buildRulesCacheKey($activeRules);
        $plan = $this->resolvePlanForRules(
            $activeRules,
            $activeRulesCacheKey,
        );

        if ($this->nestedValidation) {
            [$data, $plan] = $this->prepareNestedData(
                $data,
                $plan,
                $activeRules,
                $activeRulesCacheKey,
            );
        }

        return [$data, $plan];
    }

    /** @param array<string,array<int,string>> $errors */
    protected function throwIfValidationShouldFail(
        ValidationResult $result,
        array $errors,
    ): void {
        if (!$this->throwOnFailure || !$result->fails()) {
            return;
        }

        throw new ValidationException(
            'Validation failed',
            $errors,
            422,
        );
    }

    /**
     * @param array<int|string,mixed> $data
     * @param array{
     *   errors:array<string,array<int,string>>,
     *   failures:array<int,array{field:string,rule:string,message:string,value:mixed}>,
     *   validated:array<string,mixed>,
     *   expensiveBatch:array<int,array{
     *     rule:\Infocyph\ReqShield\Contracts\Rule,
     *     rule_name:string,
     *     value:mixed,
     *     field:string,
     *     field_label:string,
     *     message_resolver:callable(): string
     *   }>
     * } $context
     */
    protected function validateResolvedFields(
        array $data,
        ValidationPlan $plan,
        array &$context,
    ): void {
        foreach ($plan->fields as $field) {
            $node = $plan->schema[$field];
            $fieldExists = array_key_exists($field, $data);
            $value = $fieldExists ? $data[$field] : null;

            if ($this->shouldSkipOptionalField($node, $value, $fieldExists)) {
                continue;
            }

            if (!$this->processFieldValidation(
                $field,
                $value,
                $node,
                $data,
                $context,
            ) && $this->stopOnFirstError) {
                break;
            }
        }
    }
}
