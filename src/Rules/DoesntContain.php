<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

class DoesntContain extends BaseRule
{
    /** @var array<int, string> */
    protected array $values;

    public function __construct(mixed ...$values)
    {
        $this->values = array_values(array_filter(
            array_map(
                fn(mixed $item): ?string => (is_scalar($item) || (is_object($item) && method_exists($item, '__toString')))
                    ? (string) $item
                    : null,
                $values,
            ),
            static fn(?string $item): bool => $item !== null,
        ));
    }

    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The {$field} must not contain the specified values.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        // Must be a string
        if (!is_string($value)) {
            return false;
        }

        // Check that none of the specified values appear as substrings
        // Returns true only if ALL checks pass (value doesn't contain any needle)
        return array_all(
            $this->values,
            static fn(string $needle): bool => !str_contains($value, $needle),
        );
    }
}
