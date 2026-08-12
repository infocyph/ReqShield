<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Distinct Rule - Cost: 10
 * Array values must be unique (no duplicates)
 */
class Distinct extends BaseRule
{
    public function cost(): int
    {
        return 10;
    }

    public function message(string $field): string
    {
        return "The {$field} field has duplicate values.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);

        if (is_array($value)) {
            return count($value) === count(array_unique($value, SORT_REGULAR));
        }

        $segments = explode('.', $field);
        $wildcardIndexes = $this->wildcardIndexes($segments);
        if ($wildcardIndexes === []) {
            return false;
        }

        $occurrences = 0;
        foreach ($data as $candidateField => $candidate) {
            if (!$this->matchesWildcardValue($candidateField, $candidate, $value, $segments, $wildcardIndexes)) {
                continue;
            }

            ++$occurrences;
        }

        return $occurrences === 1;
    }

    /**
     * @param list<string> $segments
     * @param list<int> $wildcardIndexes
     */
    private function matchesWildcardValue(
        int|string $candidateField,
        mixed $candidate,
        mixed $value,
        array $segments,
        array $wildcardIndexes,
    ): bool {
        if (!is_string($candidateField) || $candidate !== $value) {
            return false;
        }

        $candidateSegments = explode('.', $candidateField);
        if (count($candidateSegments) !== count($segments)) {
            return false;
        }

        foreach ($segments as $index => $segment) {
            if (in_array($index, $wildcardIndexes, true)) {
                if (!ctype_digit($candidateSegments[$index])) {
                    return false;
                }

                continue;
            }

            if ($candidateSegments[$index] !== $segment) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $segments
     * @return list<int>
     */
    private function wildcardIndexes(array $segments): array
    {
        return array_keys(array_filter($segments, ctype_digit(...)));
    }
}
