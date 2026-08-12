<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Services;

use Infocyph\ReqShield\Support\NestedValidator;

final class SanitizerMapApplier
{
    /**
     * @param array<int|string, mixed> $data
     * @param array<string,list<callable(mixed):mixed>> $sanitizerMap
     * @param callable(mixed,list<callable(mixed):mixed>):mixed $applyPipeline
     * @param callable(string): string $wildcardPatternToRegex
     * @return array<int|string, mixed>
     */
    public function apply(
        array $data,
        array $sanitizerMap,
        callable $applyPipeline,
        callable $wildcardPatternToRegex,
    ): array {
        if ($sanitizerMap === []) {
            return $data;
        }

        $data = $this->applyDirectFieldSanitizers(
            $data,
            $sanitizerMap,
            $applyPipeline,
        );

        return $this->applyWildcardFieldSanitizers(
            $data,
            $sanitizerMap,
            $applyPipeline,
            $wildcardPatternToRegex,
        );
    }

    /**
     * @param array<int|string, mixed> $data
     * @param array<string,list<callable(mixed):mixed>> $sanitizerMap
     * @param callable(mixed,list<callable(mixed):mixed>):mixed $applyPipeline
     * @return array<int|string, mixed>
     */
    protected function applyDirectFieldSanitizers(
        array $data,
        array $sanitizerMap,
        callable $applyPipeline,
    ): array {
        foreach ($sanitizerMap as $field => $pipeline) {
            if (str_contains($field, '*')) {
                continue;
            }

            if ($pipeline === []) {
                continue;
            }

            $this->applyFieldSanitizer(
                $data,
                $field,
                $pipeline,
                $applyPipeline,
            );
        }

        return $data;
    }

    /**
     * @param array<int|string, mixed> $data
     * @param list<callable(mixed):mixed> $pipeline
     * @param callable(mixed,list<callable(mixed):mixed>):mixed $applyPipeline
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
     * @param array<int|string, mixed> $data
     * @param array<string,list<callable(mixed):mixed>> $sanitizerMap
     * @param callable(mixed,list<callable(mixed):mixed>):mixed $applyPipeline
     * @param callable(string): string $wildcardPatternToRegex
     * @return array<int|string, mixed>
     */
    protected function applyWildcardFieldSanitizers(
        array $data,
        array $sanitizerMap,
        callable $applyPipeline,
        callable $wildcardPatternToRegex,
    ): array {
        if (!$this->hasWildcardSanitizers($sanitizerMap)) {
            return $data;
        }

        $flattened = NestedValidator::flattenData($data);

        foreach ($sanitizerMap as $field => $pipeline) {
            if (!str_contains($field, '*')) {
                continue;
            }

            if ($pipeline === []) {
                continue;
            }

            $regex = $wildcardPatternToRegex($field);
            if ($regex === '') {
                continue;
            }

            foreach ($flattened as $path => $value) {
                if (preg_match($regex, (string) $path) !== 1) {
                    continue;
                }

                $flattened[$path] = $applyPipeline($value, $pipeline);
            }
        }

        return NestedValidator::unflattenData($flattened);
    }

    /** @param array<string,list<callable(mixed):mixed>> $sanitizerMap */
    protected function hasWildcardSanitizers(array $sanitizerMap): bool
    {
        return array_any(
            array_keys($sanitizerMap),
            static fn(string $field): bool => str_contains($field, '*'),
        );
    }
}
