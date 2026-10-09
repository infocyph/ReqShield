<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Concerns;

use Infocyph\ReqShield\Exceptions\InputLimitException;
use Infocyph\ReqShield\Exceptions\UnsupportedRequestObjectException;
use Infocyph\ReqShield\Support\NestedValidator;
use Infocyph\ReqShield\Support\ValidationContext;
use Infocyph\ReqShield\Support\ValidationPlan;

trait HasValidatorRequestFeatures
{
    /** @return array<int|string,mixed> */
    protected static function normalizeRequestPayload(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof \Traversable) {
            return iterator_to_array($value);
        }

        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                $result = $value->toArray();

                return is_array($result) ? $result : [];
            }

            return get_object_vars($value);
        }

        return [];
    }

    /** @return array<int|string,mixed> */
    protected static function serverRequestData(object $request): array
    {
        $payload = [];
        $hasAccessor = false;

        if (is_callable([$request, 'getQueryParams'])) {
            $hasAccessor = true;
            $payload = array_replace($payload, static::normalizeRequestPayload($request->getQueryParams()));
        }

        if (is_callable([$request, 'getParsedBody'])) {
            $hasAccessor = true;
            $payload = array_replace($payload, static::normalizeRequestPayload($request->getParsedBody()));
        }

        if (is_callable([$request, 'getUploadedFiles'])) {
            $hasAccessor = true;
            $payload = array_replace($payload, static::normalizeRequestPayload($request->getUploadedFiles()));
        }

        if (is_callable([$request, 'getAttributes'])) {
            $hasAccessor = true;
            $payload = array_replace($payload, static::normalizeRequestPayload($request->getAttributes()));
        }

        if (!$hasAccessor) {
            throw UnsupportedRequestObjectException::missingRequestAccessors();
        }

        return $payload;
    }

    protected function assertInputDepthAllowed(int $depth): void
    {
        if ($depth > $this->maxDepth) {
            throw new InputLimitException("Maximum input depth of {$this->maxDepth} exceeded.");
        }
    }

    /** @param array<int|string,mixed> $data */
    protected function assertInputWithinLimits(array $data): void
    {
        $fields = 0;
        $hasDottedKey = false;
        /** @var list<array{array<int|string,mixed>,int}> $stack */
        $stack = [[$data, 1]];

        while ($stack !== []) {
            [$current, $depth] = array_pop($stack);
            $this->assertInputDepthAllowed($depth);

            foreach ($current as $key => $value) {
                $hasDottedKey = $hasDottedKey || str_contains((string) $key, '.');

                ++$fields;
                if ($fields > $this->maxInputFields) {
                    throw new InputLimitException("Maximum input field count of {$this->maxInputFields} exceeded.");
                }

                if (is_array($value) && $value !== []) {
                    $stack[] = [$value, $depth + 1];
                }
            }
        }

        if ($hasDottedKey) {
            NestedValidator::assertNoConflictingPaths($data);
        }
    }

    /**
     * @param array<int|string,mixed> $data
     * @param array{
     *   errors:array<string,array<int,string>>,
     *   failures:array<int,array{field:string,rule:string,message:string,value:mixed}>,
     *   validated:array<string,mixed>,
     *   expensiveBatch:array<int,mixed>
     * } $context
     */
    protected function executeAfterValidationCallbacks(
        array $data,
        array &$context,
    ): void {
        if ($this->afterCallbacks === []) {
            return;
        }

        $errors = &$context['errors'];
        $failures = &$context['failures'];
        $validated = &$context['validated'];
        $validationContext = new ValidationContext(
            $data,
            $errors,
            $failures,
            $validated,
        );

        foreach ($this->afterCallbacks as $callback) {
            $this->invokeCallbackWithSupportedArity(
                $callback,
                [$validationContext, $this, $data],
            );
        }

    }

    /** @param array<int,string> $patterns */
    protected function matchesWildcardPattern(string $field, array $patterns): bool
    {
        return array_any(
            $patterns,
            static fn(string $pattern): bool => preg_match($pattern, $field) === 1,
        );
    }

    /**
     * @param array<int|string,mixed> $originalData
     * @param array<int|string,mixed> $preparedData
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
    protected function processUnknownFields(
        array $originalData,
        array &$preparedData,
        ValidationPlan $plan,
        array &$context,
    ): void {
        if ($this->allowUnknownFields) {
            return;
        }

        $unknownFields = $this->unknownFields($originalData, $plan);
        if ($unknownFields === []) {
            return;
        }

        if ($this->stripUnknownFields) {
            foreach ($unknownFields as $field) {
                $this->removeNestedPath($preparedData, $field);
            }

            return;
        }

        foreach ($unknownFields as $field) {
            $message = "The {$field} field is not allowed.";
            $context['errors'][$field][] = $message;
            $context['failures'][] = [
                'field' => $field,
                'rule' => 'unknown',
                'message' => $message,
                'value' => $this->unknownFieldValue($originalData, $field),
            ];

            if ($this->stopOnFirstError) {
                break;
            }
        }
    }

    /** @param array<string,mixed> $validated */
    protected function pruneParentPath(array &$validated, string $field): void
    {
        $segments = explode('.', $field);
        $count = count($segments);

        for ($index = 1; $index < $count; ++$index) {
            $parent = implode('.', array_slice($segments, 0, $index));
            if (!isset($validated[$parent]) || !is_array($validated[$parent])) {
                continue;
            }

            $this->removeNestedPath($validated[$parent], implode('.', array_slice($segments, $index)));
        }
    }

    /**
     * @param array<string,mixed> $validated
     * @param array<int,string> $fields
     * @param array<string,array<int,string>> $errors
     */
    protected function purgeValidatedDescendants(array &$validated, array $fields, array $errors): void
    {
        $hasValidatedParents = false;
        foreach ($validated as $value) {
            if (is_array($value)) {
                $hasValidatedParents = true;

                break;
            }
        }

        foreach (array_keys($errors) as $field) {
            $field = (string) $field;
            unset($validated[$field]);
            if ($hasValidatedParents && str_contains($field, '.')) {
                $this->pruneParentPath($validated, $field);
            }
        }

        if (!$hasValidatedParents) {
            return;
        }

        foreach ($fields as $field) {
            if (!str_contains($field, '.') || array_key_exists($field, $validated)) {
                continue;
            }

            $this->pruneParentPath($validated, $field);
        }
    }

    /** @param array<int|string,mixed> $data */
    protected function removeNestedPath(array &$data, string $path): void
    {
        $segments = explode('.', $path);
        $current = &$data;

        foreach ($segments as $index => $segment) {
            unset($current[implode('.', array_slice($segments, $index))]);

            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                return;
            }

            $current = &$current[$segment];
        }
    }

    /**
     * @param array<int|string,mixed> $data
     * @return array<int,string>
     */
    protected function unknownFields(array $data, ValidationPlan $plan): array
    {
        $fields = $plan->hasNestedRules
            ? array_keys(NestedValidator::flattenData($data))
            : array_keys($data);
        $unknown = [];

        foreach ($fields as $field) {
            $field = (string) $field;

            if (isset($plan->allowedFieldLookup[$field])
                || isset($plan->allowedPrefixLookup[$field])
                || $this->matchesWildcardPattern($field, $plan->wildcardRegexes)
                || $this->matchesWildcardPattern($field, $plan->wildcardPrefixRegexes)) {
                continue;
            }

            $unknown[] = $field;
        }

        return $unknown;
    }

    /**
     * @param array<int|string,mixed> $data
     */
    protected function unknownFieldValue(array $data, string $field): mixed
    {
        if (array_key_exists($field, $data)) {
            return $data[$field];
        }

        if (str_contains($field, '.')) {
            return NestedValidator::extractValue($data, $field);
        }

        return $data[$field] ?? null;
    }
}
