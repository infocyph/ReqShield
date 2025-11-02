<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * RequiredWithAll Rule - Cost: 2
 * Field is required if all of the other fields are present
 */
class RequiredWithAll extends BaseRule
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
        ) . ' are present.';
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $hasAllFields = array_all(
            $this->fields,
            fn ($otherField) => !(!isset($data[$otherField]) || $this->isEmpty(
                $data[$otherField],
            )),
        );
        if (!$hasAllFields) {
            return true;
        }

        return !$this->isEmpty($value);
    }

}
