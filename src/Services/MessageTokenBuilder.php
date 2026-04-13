<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Services;

final class MessageTokenBuilder
{
    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $rulePlaceholders
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
            if (!is_string($token) || $token === '') {
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
     * @param array<string,mixed> $tokens
     */
    protected function appendOtherToken(
        array &$tokens,
        object $rule,
        callable $resolveAlias,
    ): void {
        if (method_exists($rule, 'getOtherField')) {
            $otherField = $rule->getOtherField();
            if (is_string($otherField) && $otherField !== '') {
                $tokens['other'] = $resolveAlias($otherField);

                return;
            }
        }

        if (!method_exists($rule, 'getOtherFields')) {
            return;
        }

        $otherFields = $rule->getOtherFields();
        if (!is_array($otherFields) || $otherFields === []) {
            return;
        }

        $tokens['other'] = implode(', ', array_map(
            static fn(mixed $other): string => $resolveAlias((string) $other),
            $otherFields,
        ));
    }
}
