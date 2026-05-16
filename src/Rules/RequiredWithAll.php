<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * RequiredWithAll Rule - Cost: 2
 * Field is required if all of the other fields are present
 */
class RequiredWithAll extends AbstractRequiredRelatedFieldsRule
{
    public function message(string $field): string
    {
        return "The {$field} field is required when {$this->joinedFields()} are present.";
    }

    protected function isRequiredFromRelatedFields(array $data): bool
    {
        return array_all(
            $this->fields,
            fn(string $otherField): bool => $this->hasNonEmptyField($otherField, $data),
        );
    }
}
