<?php

declare(strict_types=1);

namespace Infocyph\ReqShield;

use Infocyph\ReqShield\Concerns\HasValidatorInternals;
use Infocyph\ReqShield\Concerns\HasValidatorRequestFeatures;
use Infocyph\ReqShield\Concerns\HasValidatorRuntime;
use Infocyph\ReqShield\Concerns\HasValidatorSchemaCasting;
use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Contracts\Rule as RuleContract;
use Infocyph\ReqShield\Exceptions\DatabaseProviderRequiredException;
use Infocyph\ReqShield\Exceptions\InputLimitException;
use Infocyph\ReqShield\Exceptions\InvalidSchemaException;
use Infocyph\ReqShield\Exceptions\ValidationException;
use Infocyph\ReqShield\Executors\BatchExecutor;
use Infocyph\ReqShield\Services\JsonSchemaExporter;
use Infocyph\ReqShield\Services\MessageTokenBuilder;
use Infocyph\ReqShield\Services\SanitizerMapApplier;
use Infocyph\ReqShield\Support\FieldAlias;
use Infocyph\ReqShield\Support\FieldPlan;
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
    use HasValidatorRequestFeatures;
    use HasValidatorRuntime;
    use HasValidatorSchemaCasting;

    protected const MAX_COMPILED_SCHEMA_CACHE = 64;

    protected const MAX_PROCESS_PLAN_CACHE = 64;

    protected const MAX_WILDCARD_SCHEMA_CACHE = 64;

    /** @var array<int|string,array<int|string,mixed>> */
    protected static array $fragments = [];

    /** @var array<string,ValidationPlan> */
    protected static array $processPlanCache = [];

    /** @var array<int,callable> */
    protected array $afterCallbacks = [];

    protected bool $allowUnknownFields = true;

    protected BatchExecutor $batchExecutor;

    /** @var array<string,list<callable(mixed):mixed>> */
    protected array $casts = [];

    /** @var array<string,string> */
    protected array $castWildcardRegexes = [];

    /** @var array<string,ValidationPlan> */
    protected array $compiledSchemaCache = [];

    /** @var array<string,list<callable(mixed):mixed>> */
    protected array $compiledSchemaCasts = [];

    /** @var array<string,list<callable(mixed):mixed>> */
    protected array $compiledSchemaSanitizers = [];

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

    /** @var array<string,list<callable(mixed):mixed>> */
    protected array $effectiveSanitizers = [];

    protected bool $failFast = true;

    /** @var array<string,string> */
    protected array $fieldAliases = [];

    protected FieldAlias $fieldAliasResolver;

    protected JsonSchemaExporter $jsonSchemaExporter;

    protected string $locale = 'en';

    /** @var list<string> */
    protected array $localeCandidates = ['en'];

    protected bool $localeMessagesEnabled = false;

    /** @var array<string,array<string,mixed>> */
    protected array $localePacks;

    protected int $maxDepth = 32;

    protected int $maxFlattenedPaths = 10_000;

    protected int $maxInputFields = 10_000;

    protected int $maxWildcardExpansions = 10_000;

    protected MessageTokenBuilder $messageTokenBuilder;

    protected string $nestedFlattenMode = 'targeted';

    /** @var array<int|string,mixed> */
    protected array $rules;

    protected string $rulesCacheKey;

    protected SanitizerMapApplier $sanitizerMapApplier;

    /** @var array<string,list<callable(mixed):mixed>> */
    protected array $sanitizers = [];

    /** @var array<string,string> */
    protected array $sanitizerWildcardRegexes = [];

    /** @var array<int|string,mixed> */
    protected array $schema;

    /** @var array<string,mixed> */
    protected array $schemaCasts;

    /** @var array<string,mixed> */
    protected array $schemaSanitizers;

    protected bool $stopOnFirstError = false;

    protected bool $stripUnknownFields = false;

    protected bool $throwOnFailure = false;

    protected ValidationPlan $validationPlan;

    /** @var array<int,array<string,mixed>> */
    protected array $whenCallbacks = [];

    /** @var array<string,ValidationPlan> */
    protected array $wildcardSchemaCache = [];

    /**
     * @param array<int|string,mixed> $rules
     * @param array<string,class-string<RuleContract>> $customRules
     */
    public function __construct(
        array $rules,
        ?DatabaseProvider $db = null,
        ?ValidationPlan $compiledPlan = null,
        array $customRules = [],
    ) {
        if (empty($rules)) {
            throw InvalidSchemaException::forField(
                'rules',
                'Rules array cannot be empty',
            );
        }

        foreach ($rules as $field => $rule) {
            if (!is_string($field)) {
                throw InvalidSchemaException::forField(
                    (string) $field,
                    'Field names must be strings',
                );
            }

            if (!is_string($rule) && !is_array($rule) && !$rule instanceof RuleContract) {
                throw InvalidSchemaException::forField(
                    $field,
                    'Rules must be a rule object, string, or array',
                );
            }
        }

        [$normalizedRules, $schemaSanitizers, $schemaCasts] = $this->normalizeRuleDefinitions($rules);
        $this->rules = $normalizedRules;
        $this->schemaSanitizers = $schemaSanitizers;
        $this->schemaCasts = $schemaCasts;
        $this->compiledSchemaSanitizers = $this->compileSanitizerMap($schemaSanitizers);
        $this->compiledSchemaCasts = $this->compileCastMap($schemaCasts);
        $this->refreshSanitizerExecutionMetadata();
        $this->refreshCastExecutionMetadata();
        $this->rulesCacheKey = $this->buildRulesCacheKey($normalizedRules);
        $this->localePacks = $this->defaultLocalePacks();
        $this->compiler = new SchemaCompiler();
        foreach ($customRules as $name => $class) {
            $this->compiler->registerRule($name, $class);
        }
        $this->validationPlan = $compiledPlan ?? ($customRules === []
            ? $this->resolveInitialPlan($normalizedRules)
            : new ValidationPlan($this->normalizeCompiledSchema(
                $this->compiler->compile($normalizedRules),
            )));
        $this->schema = $this->validationPlan->schema;
        $this->assertDatabaseProviderAvailable($this->validationPlan, $db !== null);
        $this->fieldAliasResolver = new FieldAlias($this->fieldAliases);
        $this->messageTokenBuilder = new MessageTokenBuilder();
        $this->jsonSchemaExporter = new JsonSchemaExporter();
        $this->sanitizerMapApplier = new SanitizerMapApplier();
        $this->batchExecutor = new BatchExecutor($db);
    }

    public static function clearFragments(): void
    {
        static::$fragments = [];
    }

    public static function clearPlanCache(): void
    {
        static::$processPlanCache = [];
    }

    /**
     * @param array<int|string,mixed> $rules
     * @param array<string,class-string<RuleContract>> $customRules
     */
    public static function compile(
        array $rules,
        ?DatabaseProvider $db = null,
        array $customRules = [],
    ): CompiledValidator {
        $validator = static::make($rules, $db, $customRules);

        return new CompiledValidator($validator);
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
        if (isset(static::$fragments[$name])) {
            throw InvalidSchemaException::forField('fragment', "Schema fragment already exists: {$name}");
        }

        static::$fragments[$name] = $rules;
    }

    /** @return array<int|string,mixed> */
    public static function fragment(string $name, string $prefix = ''): array
    {
        if (!isset(static::$fragments[$name])) {
            throw InvalidSchemaException::forField('fragment', "Unknown schema fragment: {$name}");
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

    /**
     * @param array<int|string,mixed> $rules
     * @param array<int|string,mixed> $data
     */
    public static function fromArray(
        array $rules,
        array $data,
        ?DatabaseProvider $db = null,
    ): ValidationResult {
        return static::make($rules, $db)->validate($data);
    }

    /**
     * @param array<int|string,mixed> $rules
     * @param array<int|string,mixed> $body
     */
    public static function fromBody(
        array $rules,
        array $body,
        ?DatabaseProvider $db = null,
    ): ValidationResult {
        return static::fromArray($rules, $body, $db);
    }

    /**
     * @param array<int|string,mixed> $rules
     * @param array<int|string,mixed> $files
     */
    public static function fromFiles(
        array $rules,
        array $files,
        ?DatabaseProvider $db = null,
    ): ValidationResult {
        return static::fromArray($rules, $files, $db);
    }

    /**
     * @param array<int|string,mixed> $rules
     * @param array<int|string,mixed> $query
     */
    public static function fromQuery(
        array $rules,
        array $query,
        ?DatabaseProvider $db = null,
    ): ValidationResult {
        return static::fromArray($rules, $query, $db);
    }

    /** @param array<int|string,mixed> $rules */
    public static function fromServerRequest(
        array $rules,
        object $request,
        ?DatabaseProvider $db = null,
    ): ValidationResult {
        return static::fromArray($rules, static::serverRequestData($request), $db);
    }

    public static function hasFragment(string $name): bool
    {
        return array_key_exists($name, static::$fragments);
    }

    /**
     * @param array<int|string,mixed> $rules
     * @param array<string,class-string<RuleContract>> $customRules
     */
    public static function make(
        array $rules,
        ?DatabaseProvider $db = null,
        array $customRules = [],
    ): self {
        return new static($rules, $db, null, $customRules);
    }

    /** @param array<string,string> $messages */
    public function addLocalePack(string $locale, array $messages): self
    {
        $this->localePacks[$locale] = $messages;
        $this->localeMessagesEnabled = true;

        return $this;
    }

    public function after(callable $callback): self
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    public function allowUnknown(bool $allow = true): self
    {
        $this->allowUnknownFields = $allow;
        if ($allow) {
            $this->stripUnknownFields = false;
        }

        return $this;
    }

    public function enableNestedValidation(bool $flattenAll = true): self
    {
        $this->nestedFlattenMode = $flattenAll ? 'all' : 'targeted';

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
            default => throw InvalidSchemaException::forField('export', "Unsupported schema format: {$format}"),
        };
    }

    /** @return array<int|string,mixed> */
    public function getSchemaStats(): array
    {
        $stats = [
            'total_fields' => count($this->schema),
            'fields' => [],
        ];

        foreach ($this->schema as $field => $node) {
            if (!is_string($field) || !$node instanceof FieldPlan) {
                continue;
            }

            $stats['fields'][$field] = $node->getStats();
        }

        return $stats;
    }

    public function limits(
        int $maxDepth = 32,
        int $maxFields = 10_000,
        int $maxWildcardExpansions = 10_000,
        int $maxFlattenedPaths = 10_000,
    ): self {
        if (min($maxDepth, $maxFields, $maxWildcardExpansions, $maxFlattenedPaths) < 1) {
            throw new \InvalidArgumentException('Validation limits must be positive integers.');
        }

        $this->maxDepth = $maxDepth;
        $this->maxInputFields = $maxFields;
        $this->maxWildcardExpansions = $maxWildcardExpansions;
        $this->maxFlattenedPaths = $maxFlattenedPaths;

        return $this;
    }

    /** @return array<string,array<string,mixed>> */
    public function schemaIntrospection(): array
    {
        $meta = [];

        foreach ($this->schema as $field => $node) {
            if (!is_string($field) || !$node instanceof FieldPlan) {
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
        $this->casts = $this->compileCastMap($casts);
        $this->refreshCastExecutionMetadata();

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
        if ($class !== null) {
            if (!class_exists($class)) {
                throw InvalidSchemaException::forField('dto', "DTO class does not exist: {$class}");
            }

            $reflection = new \ReflectionClass($class);
            if (!$reflection->isInstantiable()) {
                throw InvalidSchemaException::forField('dto', "DTO class is not instantiable: {$class}");
            }
        }

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
        $this->localeCandidates = $this->localeCandidates($locale);
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
        if ($mode === 'required') {
            $mode = 'targeted';
        }

        if (!in_array($mode, ['all', 'targeted'], true)) {
            throw InvalidSchemaException::forField('nested mode', "Unsupported mode: {$mode}");
        }

        $this->nestedFlattenMode = $mode;

        return $this;
    }

    /** @param array<string,string|callable|array<int,string|callable>> $sanitizers */
    public function setSanitizers(array $sanitizers): self
    {
        $this->sanitizers = $this->compileSanitizerMap($sanitizers);
        $this->refreshSanitizerExecutionMetadata();

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

    public function strict(): self
    {
        return $this->allowUnknown(false);
    }

    public function stripUnknown(bool $strip = true): self
    {
        $this->stripUnknownFields = $strip;
        if ($strip) {
            $this->allowUnknownFields = false;
        }

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
        foreach ($fragmentSanitizers as $field => $pipeline) {
            $this->schemaSanitizers[$field] = array_merge(
                static::normalizePipelineDefinition($this->schemaSanitizers[$field] ?? []),
                static::normalizePipelineDefinition($pipeline),
            );
        }
        $this->schemaCasts = array_merge($this->schemaCasts, $fragmentCasts);
        $this->compiledSchemaSanitizers = $this->compileSanitizerMap($this->schemaSanitizers);
        $this->compiledSchemaCasts = array_merge(
            $this->compiledSchemaCasts,
            $this->compileCastMap($fragmentCasts),
        );
        $this->refreshSanitizerExecutionMetadata();
        $this->refreshCastExecutionMetadata();
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
        $this->assertInputWithinLimits($data);
        $originalData = $data;
        [$data, $plan] = $this->prepareValidationDataAndSchema($data);
        $context = $this->initializeValidationContext();
        $this->processUnknownFields($originalData, $data, $plan, $context);

        if (!empty($context['errors']) && $this->stopOnFirstError) {
            $result = $this->buildValidationResult($context);
            $this->throwIfValidationShouldFail($result, $context['errors']);

            return $result;
        }

        foreach ($plan->fields as $field) {
            $fieldPlan = $plan->schema[$field];
            $value = array_key_exists($field, $data) ? $data[$field] : null;
            if (!$this->processFieldValidation($field, $value, $fieldPlan, $data, $context)
                && $this->stopOnFirstError) {
                break;
            }
        }
        $this->executeBatchedRules($context);
        $this->executeAfterValidationCallbacks($data, $context);
        $result = $this->buildValidationResult($context);
        $this->throwIfValidationShouldFail($result, $context['errors']);

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

    protected function assertDatabaseProviderAvailable(ValidationPlan $plan, bool $available): void
    {
        if (!$plan->requiresDatabase || $available) {
            return;
        }

        foreach ($plan->schema as $fieldPlan) {
            if ($fieldPlan->batchRuleNames !== []) {
                throw DatabaseProviderRequiredException::forRule($fieldPlan->batchRuleNames[0]);
            }
        }
    }

    /** @param array<int|string,mixed> $data */
    protected function assertInputWithinLimits(array $data): void
    {
        $fields = 0;
        /** @var list<array{array<int|string,mixed>,int}> $stack */
        $stack = [[$data, 1]];

        while ($stack !== []) {
            [$current, $depth] = array_pop($stack);
            if ($depth > $this->maxDepth) {
                throw new InputLimitException("Maximum input depth of {$this->maxDepth} exceeded.");
            }

            foreach ($current as $value) {
                ++$fields;
                if ($fields > $this->maxInputFields) {
                    throw new InputLimitException("Maximum input field count of {$this->maxInputFields} exceeded.");
                }

                if (is_array($value) && $value !== []) {
                    $stack[] = [$value, $depth + 1];
                }
            }
        }
    }

    /**
     * @param array{
     *   errors:array<string,array<int,string>>,
     *   failures:array<int,array{field:string,rule:string,message:string,value:mixed}>,
     *   validated:array<string,mixed>,
     *   expensiveBatch:array<int,mixed>
     * } $context
     */
    protected function buildValidationResult(array $context): ValidationResult
    {
        return new ValidationResult(
            $context['errors'],
            $context['validated'],
            $context['failures'],
            $this->applyCasts($context['validated']),
            $this->dtoClass,
        );
    }

    /** @param array<int|string,mixed> $rules */
    protected function isProcessCacheSafeSchema(array $rules): bool
    {
        foreach ($rules as $definition) {
            if (is_string($definition)) {
                continue;
            }

            if (!is_array($definition)) {
                return false;
            }

            foreach ($definition as $rule) {
                if (!is_string($rule)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param array<int|string,mixed> $schema
     *
     * @return array<string,FieldPlan>
     */
    protected function normalizeCompiledSchema(array $schema): array
    {
        $normalized = [];

        foreach ($schema as $field => $node) {
            if (!is_string($field) || !$node instanceof FieldPlan) {
                continue;
            }

            $normalized[$field] = $node;
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

        $this->assertDatabaseProviderAvailable($plan, $this->batchExecutor->hasProvider());

        if ($plan->hasNestedRules) {
            [$data, $plan] = $this->prepareNestedData(
                $data,
                $plan,
                $activeRules,
                $activeRulesCacheKey,
            );
        }

        return [$data, $plan];
    }

    /**
     * @param array<int|string,mixed> $rules
     */
    protected function resolveInitialPlan(array $rules): ValidationPlan
    {
        if (!$this->isProcessCacheSafeSchema($rules)) {
            return new ValidationPlan($this->normalizeCompiledSchema(
                $this->compiler->compile($rules),
            ));
        }

        $key = $this->buildRulesCacheKey($rules);
        if (isset(static::$processPlanCache[$key])) {
            return static::$processPlanCache[$key];
        }

        $plan = new ValidationPlan($this->normalizeCompiledSchema(
            $this->compiler->compile($rules),
        ));
        static::$processPlanCache[$key] = $plan;

        if (count(static::$processPlanCache) > static::MAX_PROCESS_PLAN_CACHE) {
            array_shift(static::$processPlanCache);
        }

        return $plan;
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
}
