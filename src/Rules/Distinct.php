<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Distinct Rule - Cost: 10
 * Array values or members of one expanded wildcard group must be unique.
 */
class Distinct extends BaseRule
{
    public function __construct(private ?string $wildcardPattern = null) {}

    public function cost(): int
    {
        return 10;
    }

    public function message(string $field): string
    {
        return "The {$field} field has duplicate values.";
    }

    public function forPattern(string $pattern): self
    {
        $copy = clone $this;
        $copy->wildcardPattern = $pattern;

        return $copy;
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);

        if (is_array($value)) {
            return count($value) === count(array_unique($value, SORT_REGULAR));
        }

        if ($this->wildcardPattern === null) {
            return false;
        }

        $pattern = explode('.', $this->wildcardPattern);
        $fieldParts = explode('.', $field);
        if (count($fieldParts) !== count($pattern)) {
            return false;
        }

        $wildcardPositions = array_keys(array_filter(
            $pattern,
            static fn(string $segment): bool => $segment === '*',
        ));
        if ($wildcardPositions === []) {
            return false;
        }

        $lastWildcard = $wildcardPositions[count($wildcardPositions) - 1];
        $occurrences = 0;

        foreach ($data as $candidateField => $candidate) {
            if (!is_string($candidateField) || $candidate !== $value) {
                continue;
            }

            $candidateParts = explode('.', $candidateField);
            if (count($candidateParts) !== count($pattern)) {
                continue;
            }

            $matches = true;
            foreach ($pattern as $index => $segment) {
                if ($segment === '*') {
                    if ($index !== $lastWildcard && $candidateParts[$index] !== $fieldParts[$index]) {
                        $matches = false;
                        break;
                    }

                    continue;
                }

                if ($candidateParts[$index] !== $segment) {
                    $matches = false;
                    break;
                }
            }

            if ($matches && ++$occurrences > 1) {
                return false;
            }
        }

        return $occurrences === 1;
    }
}
