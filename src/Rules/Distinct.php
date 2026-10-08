<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Distinct Rule - Cost: 10
 * Array values or members of one expanded wildcard group must be unique.
 */
class Distinct extends BaseRule
{
    public function __construct(private ?string $wildcardPattern = null)
    {
    }

    public function cost(): int
    {
        return 10;
    }

    public function forPattern(string $pattern): self
    {
        $copy = clone $this;
        $copy->wildcardPattern = $pattern;

        return $copy;
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

        return $this->wildcardPattern !== null
            && $this->hasUniqueWildcardOccurrence($value, $field, $data, explode('.', $this->wildcardPattern));
    }

    /** @param list<string> $pattern */
    private function hasUniqueWildcardOccurrence(mixed $value, string $field, array $data, array $pattern): bool
    {
        $fieldParts = explode('.', $field);
        if (count($fieldParts) !== count($pattern)) {
            return false;
        }

        $wildcards = array_keys(array_filter($pattern, static fn(string $segment): bool => $segment === '*'));
        if ($wildcards === []) {
            return false;
        }

        $lastWildcard = $wildcards[count($wildcards) - 1];
        $occurrences = 0;

        foreach ($data as $candidateField => $candidate) {
            if (!is_string($candidateField) || $candidate !== $value
                || !$this->matchesDistinctGroup($candidateField, $fieldParts, $pattern, $lastWildcard)) {
                continue;
            }

            if (++$occurrences > 1) {
                return false;
            }
        }

        return $occurrences === 1;
    }

    /** @param list<string> $fieldParts
     *  @param list<string> $pattern
     */
    private function matchesDistinctGroup(
        string $candidateField,
        array $fieldParts,
        array $pattern,
        int $lastWildcard,
    ): bool {
        $candidateParts = explode('.', $candidateField);
        if (count($candidateParts) !== count($pattern)) {
            return false;
        }

        foreach ($pattern as $index => $segment) {
            if ($segment === '*' && $index === $lastWildcard) {
                continue;
            }

            if ($candidateParts[$index] !== ($segment === '*' ? $fieldParts[$index] : $segment)) {
                return false;
            }
        }

        return true;
    }
}
