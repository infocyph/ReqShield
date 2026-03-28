<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Services;

use Infocyph\ReqShield\Support\NestedValidator;

final class SanitizerMapApplier
{
    /**
     * @param array<string,mixed> $sanitizerMap
     */
    public function apply(
        array $data,
        array $sanitizerMap,
        callable $normalizePipeline,
        callable $applyPipeline,
        callable $wildcardPatternToRegex,
    ): array {
        if ($sanitizerMap === []) {
            return $data;
        }

        $data = $this->applyDirectFieldSanitizers(
            $data,
            $sanitizerMap,
            $normalizePipeline,
            $applyPipeline,
        );

        return $this->applyWildcardFieldSanitizers(
            $data,
            $sanitizerMap,
            $normalizePipeline,
            $applyPipeline,
            $wildcardPatternToRegex,
        );
    }

    /**
     * @param array<string,mixed> $sanitizerMap
     */
    protected function applyDirectFieldSanitizers(
        array $data,
        array $sanitizerMap,
        callable $normalizePipeline,
        callable $applyPipeline,
    ): array {
        foreach ($sanitizerMap as $field => $pipeline) {
            if (!is_string($field) || str_contains($field, '*')) {
                continue;
            }

            $normalizedPipeline = $normalizePipeline($pipeline);
            if (!is_array($normalizedPipeline) || $normalizedPipeline === []) {
                continue;
            }

            $this->applyFieldSanitizer(
                $data,
                $field,
                $normalizedPipeline,
                $applyPipeline,
            );
        }

        return $data;
    }

    /**
     * @param array<int,mixed> $pipeline
     */
    protected function applyFieldSanitizer(
        array &$data,
        string $field,
        array $pipeline,
        callable $applyPipeline,
    ): void {
        if (str_contains($field, '.')) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $applyPipeline($data[$field], $pipeline);

                return;
            }

            if (!NestedValidator::has($data, $field)) {
                return;
            }

            $current = NestedValidator::extractValue($data, $field);
            NestedValidator::setValue($data, $field, $applyPipeline($current, $pipeline));

            return;
        }

        if (!array_key_exists($field, $data)) {
            return;
        }

        $data[$field] = $applyPipeline($data[$field], $pipeline);
    }

    /**
     * @param array<string,mixed> $sanitizerMap
     */
    protected function applyWildcardFieldSanitizers(
        array $data,
        array $sanitizerMap,
        callable $normalizePipeline,
        callable $applyPipeline,
        callable $wildcardPatternToRegex,
    ): array {
        if (!$this->hasWildcardSanitizers($sanitizerMap)) {
            return $data;
        }

        $flattened = NestedValidator::flattenData($data);

        foreach ($sanitizerMap as $field => $pipeline) {
            if (!is_string($field) || !str_contains($field, '*')) {
                continue;
            }

            $normalizedPipeline = $normalizePipeline($pipeline);
            if (!is_array($normalizedPipeline) || $normalizedPipeline === []) {
                continue;
            }

            $regex = $wildcardPatternToRegex($field);
            if (!is_string($regex) || $regex === '') {
                continue;
            }

            foreach ($flattened as $path => $value) {
                if (preg_match($regex, $path) !== 1) {
                    continue;
                }

                $flattened[$path] = $applyPipeline($value, $normalizedPipeline);
            }
        }

        return NestedValidator::unflattenData($flattened);
    }

    /**
     * @param array<string,mixed> $sanitizerMap
     */
    protected function hasWildcardSanitizers(array $sanitizerMap): bool
    {
        return array_any(
            array_keys($sanitizerMap),
            static fn (string $field): bool => str_contains($field, '*'),
        );
    }
}
