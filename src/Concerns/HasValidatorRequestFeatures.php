<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Concerns;

use Infocyph\ReqShield\Exceptions\UnsupportedRequestObjectException;
use Infocyph\ReqShield\Support\NestedValidator;
use Infocyph\ReqShield\Support\ValidationContext;
use Infocyph\ReqShield\Support\ValidationPlan;
use Infocyph\ReqShield\Support\WildcardPath;

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

        if (method_exists($request, 'getQueryParams')) {
            $hasAccessor = true;
            $payload = array_replace($payload, static::normalizeRequestPayload($request->getQueryParams()));
        }

        if (method_exists($request, 'getParsedBody')) {
            $hasAccessor = true;
            $payload = array_replace($payload, static::normalizeRequestPayload($request->getParsedBody()));
        }

        if (method_exists($request, 'getUploadedFiles')) {
            $hasAccessor = true;
            $payload = array_replace($payload, static::normalizeRequestPayload($request->getUploadedFiles()));
        }

        if (method_exists($request, 'getAttributes')) {
            $hasAccessor = true;
            $payload = array_replace($payload, static::normalizeRequestPayload($request->getAttributes()));
        }

        if (!$hasAccessor) {
            throw UnsupportedRequestObjectException::missingRequestAccessors();
        }

        return $payload;
    }

    /**
     * @param array<int|string,mixed> $data
     * @param array<string,mixed> $context
     */
    protected function executeAfterValidationCallbacks(
        array $data,
        array &$context,
    ): void {
        if ($this->afterCallbacks === []) {
            return;
        }

        $errors = $this->normalizeContextErrors($context['errors'] ?? []);
        $failures = $this->normalizeContextFailures($context['failures'] ?? []);
        $validated = $this->normalizeContextValidated($context['validated'] ?? []);

        foreach ($this->afterCallbacks as $callback) {
            $validationContext = new ValidationContext(
                $data,
                $errors,
                $failures,
                $validated,
            );
            $this->invokeCallbackWithSupportedArity(
                $callback,
                [$validationContext, $this, $data],
            );
        }

        $context['errors'] = $errors;
        $context['failures'] = $failures;
        $context['validated'] = $validated;
    }

    /**
     * @param array<int,string> $patterns
     */
    protected function matchesWildcardPattern(string $field, array $patterns): bool
    {
        return array_any($patterns, fn(string $pattern): bool => preg_match($pattern, $field) === 1);
    }

    /** @return array<string,array<int,string>> */
    protected function normalizeContextErrors(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        return $this->normalizeErrorMap($raw);
    }

    /** @return array<int,array{field:string,rule:string,message:string,value:mixed}> */
    protected function normalizeContextFailures(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        return $this->normalizeFailurePayload($raw);
    }

    /** @return array<string,mixed> */
    protected function normalizeContextValidated(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $field => $value) {
            if (!is_string($field)) {
                continue;
            }

            $normalized[$field] = $value;
        }

        return $normalized;
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
                unset($preparedData[$field]);
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

    /**
     * @param array<int|string,mixed> $data
     * @return array<int,string>
     */
    protected function unknownFields(array $data, ValidationPlan $plan): array
    {
        $allowed = array_fill_keys($plan->fields, true);
        $wildcardPatterns = $this->wildcardRulePatterns();
        $fields = $this->nestedValidation
            ? array_keys(NestedValidator::flattenData($data))
            : array_keys($data);
        $unknown = [];

        foreach ($fields as $field) {
            if (!is_string($field)) {
                continue;
            }

            if (isset($allowed[$field]) || $this->matchesWildcardPattern($field, $wildcardPatterns)) {
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
        if ($this->nestedValidation && str_contains($field, '.')) {
            return NestedValidator::extractValue($data, $field);
        }

        return $data[$field] ?? null;
    }

    /** @return array<int,string> */
    protected function wildcardRulePatterns(): array
    {
        $patterns = [];

        foreach (array_keys($this->rules) as $field) {
            if (!is_string($field) || !str_contains($field, '*')) {
                continue;
            }

            $patterns[] = WildcardPath::toRegex($field);
        }

        return $patterns;
    }
}
