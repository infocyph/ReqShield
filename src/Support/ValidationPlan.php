<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

readonly class ValidationPlan
{
    /** @var array<string,true> */
    public array $allowedFieldLookup;

    /** @var array<string,true> */
    public array $allowedPrefixLookup;

    /** @var list<string> */
    public array $dependencyPaths;

    /** @var array<int,string> */
    public array $fields;

    public bool $hasNestedRules;

    public bool $hasWildcardRules;

    /** @var list<string> */
    public array $inputPaths;

    /** @var list<string> */
    public array $requiredPaths;

    public bool $requiresDatabase;

    /** @var list<string> */
    public array $wildcardPatterns;

    /** @var list<string> */
    public array $wildcardPrefixRegexes;

    /** @var list<string> */
    public array $wildcardRegexes;

    /** @param array<string,FieldPlan> $schema */
    public function __construct(public array $schema)
    {
        $this->fields = array_keys($this->schema);
        $hasNestedRules = false;
        $hasWildcardRules = false;
        $requiresDatabase = false;
        $requiredPaths = [];
        $dependencyPaths = [];
        $wildcardPatterns = [];

        foreach ($this->schema as $field => $plan) {
            if (str_contains($field, '.')) {
                $hasNestedRules = true;
            }

            if (str_contains($field, '*')) {
                $hasNestedRules = true;
                $hasWildcardRules = true;
                $wildcardPatterns[] = $field;
            }

            if (!$plan->isOptional || $plan->requiresValidationWhenMissing) {
                $requiredPaths[] = $field;
            }

            if ($plan->batchRules !== []) {
                $requiresDatabase = true;
            }

            $dependencyPaths = [...$dependencyPaths, ...$plan->dependencyPaths];
        }

        $this->requiredPaths = $requiredPaths;
        $this->dependencyPaths = array_values(array_unique($dependencyPaths));
        $this->inputPaths = array_values(array_unique([...$this->fields, ...$this->dependencyPaths]));
        $this->wildcardPatterns = $wildcardPatterns;
        $this->wildcardRegexes = array_map(WildcardPath::toRegex(...), $wildcardPatterns);
        $allowedPaths = array_values(array_unique([...$this->fields, ...$this->dependencyPaths]));
        $this->allowedFieldLookup = array_fill_keys($allowedPaths, true);
        [$prefixes, $wildcardPrefixPatterns] = self::allowedPrefixes($allowedPaths);
        $this->allowedPrefixLookup = $prefixes;
        $this->wildcardPrefixRegexes = array_map(
            WildcardPath::toRegex(...),
            array_keys($wildcardPrefixPatterns),
        );
        $this->hasNestedRules = $hasNestedRules;
        $this->hasWildcardRules = $hasWildcardRules;
        $this->requiresDatabase = $requiresDatabase;
    }

    /**
     * @param list<string> $paths
     * @return array{array<string,true>,array<string,true>}
     */
    private static function allowedPrefixes(array $paths): array
    {
        $prefixes = [];
        $wildcards = [];
        foreach ($paths as $path) {
            $segments = explode('.', $path);
            array_pop($segments);
            while ($segments !== []) {
                $prefix = implode('.', $segments);
                $target = str_contains($prefix, '*') ? $wildcards : $prefixes;
                $target[$prefix] = true;
                if (str_contains($prefix, '*')) {
                    $wildcards = $target;
                } else {
                    $prefixes = $target;
                }
                array_pop($segments);
            }
        }

        return [$prefixes, $wildcards];
    }
}
