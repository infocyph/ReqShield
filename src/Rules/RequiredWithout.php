<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * RequiredWithout Rule - Cost: 2
 * Field is required if any of the other fields are NOT present
 */
class RequiredWithout extends BaseRule
{
    protected array $fields;

    public function __construct(string ...$fields)
    {
        $this->fields = $fields;
    }

    public function cost(): int
    {
        return 2;
    }

    public function message(string $field): string
    {
        return "The {$field} field is required when " . implode(
            ', ',
            $this->fields,
        ) . ' is not present.';
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $missingAnyField = array_any(
            $this->fields,
            fn ($otherField) => !isset($data[$otherField]) || $this->isEmpty(
                $data[$otherField],
            ),
        );
        if (!$missingAnyField) {
            return true;
        }

        return !$this->isEmpty($value);
    }

}
