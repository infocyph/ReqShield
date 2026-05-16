<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * RequiredWith Rule - Cost: 2
 * Field is required if any of the other fields are present
 */
class RequiredWith extends AbstractRequiredRelatedFieldsRule
{
    public function message(string $field): string
    {
        return "The {$field} field is required when {$this->joinedFields()} is present.";
    }

    protected function isRequiredFromRelatedFields(array $data): bool
    {
        return array_any(
            $this->fields,
            fn(string $otherField): bool => $this->hasNonEmptyField($otherField, $data),
        );
    }
}
