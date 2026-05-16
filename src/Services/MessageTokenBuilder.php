<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Services;

final class MessageTokenBuilder
{
    /**
     * @param array<int|string, mixed> $data
     * @param array<string, mixed> $rulePlaceholders
     * @param callable(mixed): string $stringify
     * @param callable(string): string $resolveAlias
     * @param callable(string): string $normalizeOtherPlaceholder
     * @return array<string, mixed>
     */
    public function build(
        string $field,
        string $fieldLabel,
        string $ruleName,
        mixed $value,
        object $rule,
        array $data,
        array $rulePlaceholders,
        callable $stringify,
        callable $resolveAlias,
        callable $normalizeOtherPlaceholder,
    ): array {
        $tokens = [
            'field' => $fieldLabel,
            'attribute' => $fieldLabel,
            'key' => $field,
            'rule' => $ruleName,
            'value' => $stringify($value),
            'input' => $stringify($value),
        ];

        foreach ($rulePlaceholders as $token => $tokenValue) {
            if ($token === '') {
                continue;
            }

            $tokens[$token] = $tokenValue;
        }

        $this->appendOtherToken($tokens, $rule, $resolveAlias);

        if (!isset($tokens['value']) && array_key_exists($field, $data)) {
            $tokens['value'] = $stringify($data[$field]);
        }

        if (isset($tokens['other']) && is_string($tokens['other'])) {
            $tokens['other'] = $normalizeOtherPlaceholder($tokens['other']);
        }

        return $tokens;
    }

    /**
     * @param array<string, mixed> $tokens
     * @param callable(string): string $resolveAlias
     */
    protected function appendOtherToken(
        array &$tokens,
        object $rule,
        callable $resolveAlias,
    ): void {
        $single = $this->resolveSingleOtherFieldToken($rule, $resolveAlias);
        if ($single !== null) {
            $tokens['other'] = $single;

            return;
        }

        $multi = $this->resolveMultiOtherFieldToken($rule, $resolveAlias);
        if ($multi !== null) {
            $tokens['other'] = $multi;
        }
    }

    /** @param callable(string): string $resolveAlias */
    protected function resolveMultiOtherFieldToken(object $rule, callable $resolveAlias): ?string
    {
        if (!method_exists($rule, 'getOtherFields')) {
            return null;
        }

        $otherFields = $rule->getOtherFields();
        if (!is_array($otherFields) || $otherFields === []) {
            return null;
        }

        $aliases = [];
        foreach ($otherFields as $other) {
            if (!is_scalar($other) && !(is_object($other) && method_exists($other, '__toString'))) {
                continue;
            }

            $alias = $resolveAlias((string) $other);
            if ($alias !== '') {
                $aliases[] = $alias;
            }
        }

        if ($aliases === []) {
            return null;
        }

        return implode(', ', $aliases);
    }

    /** @param callable(string): string $resolveAlias */
    protected function resolveSingleOtherFieldToken(object $rule, callable $resolveAlias): ?string
    {
        if (!method_exists($rule, 'getOtherField')) {
            return null;
        }

        $otherField = $rule->getOtherField();
        if (!is_string($otherField) || $otherField === '') {
            return null;
        }

        return $resolveAlias($otherField);
    }
}
