<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * RequiredWithout Rule - Cost: 2
 * Field is required if any of the other fields are NOT present
 */
class RequiredWithout extends AbstractRequiredRelatedFieldsRule
{
    public function message(string $field): string
    {
        return "The {$field} field is required when {$this->joinedFields()} is not present.";
    }

    protected function isRequiredFromRelatedFields(array $data): bool
    {
        return array_any(
            $this->fields,
            fn(string $otherField): bool => !$this->hasNonEmptyField($otherField, $data),
        );
    }
}
